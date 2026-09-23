<?php

namespace App\Monitoring\Fetchers;

use App\Models\Source;
use App\Monitoring\FeedEntry;
use App\Monitoring\FeedParser;
use App\Monitoring\Http;
use App\Monitoring\Robots;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Generic scraper for outlets without a feed: section pages → article links
 * (config.link_selector) → article metadata (OpenGraph / JSON-LD).
 * Respects robots.txt, waits between requests to the same domain, stores
 * metadata only and never bypasses paywalls.
 *
 * config: { "sections": ["https://…"], "link_selector": "article a", "max_articles": 15 }
 */
class ScraperFetcher implements Fetcher
{
    public function __construct(private Robots $robots) {}

    public function fetch(Source $source): array
    {
        $config = $source->config ?? [];
        $sections = $config['sections'] ?? [$source->url];
        $selector = $config['link_selector'] ?? 'article a';
        $max = (int) ($config['max_articles'] ?? 15);

        $links = [];
        foreach ($sections as $section) {
            $html = $this->get($section);
            $crawler = new Crawler($html, $section);
            foreach ($crawler->filter($selector)->links() as $link) {
                $links[$link->getUri()] = true;
            }
        }

        $entries = [];
        foreach (array_slice(array_keys($links), 0, $max) as $url) {
            if (($entry = $this->article($url, $source)) !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function article(string $url, Source $source): ?FeedEntry
    {
        if (! $this->robots->allows($url)) {
            return null;
        }

        $crawler = new Crawler($this->get($url), $url);
        $meta = fn (string $property) => $crawler->filter("meta[property=\"{$property}\"], meta[name=\"{$property}\"]")->count()
            ? (string) $crawler->filter("meta[property=\"{$property}\"], meta[name=\"{$property}\"]")->first()->attr('content')
            : null;

        $headline = FeedParser::text($meta('og:title') ?? ($crawler->filter('title')->count() ? $crawler->filter('title')->text() : ''));

        if ($headline === '') {
            return null;
        }

        return new FeedEntry(
            headline: $headline,
            url: $meta('og:url') ?: $url,
            summary: FeedParser::summary($meta('og:description') ?? $meta('description') ?? ''),
            publishedAt: FeedParser::date($meta('article:published_time') ?? ''),
            outlet: $source->name,
        );
    }

    private function get(string $url): string
    {
        if (! $this->robots->allows($url)) {
            throw new RuntimeException("robots.txt does not allow {$url}");
        }

        // At most one request every 2 seconds per domain.
        $host = (string) parse_url($url, PHP_URL_HOST);
        Cache::lock('scrape:'.$host, 10)->block(10, function () use ($host) {
            $last = (float) Cache::get('scrape-last:'.$host, 0);
            $wait = 2 - (microtime(true) - $last);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
            Cache::put('scrape-last:'.$host, microtime(true), 60);
        });

        $response = Http::client()->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()} from {$url}");
        }

        return $response->body();
    }
}
