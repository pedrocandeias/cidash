<?php

namespace App\Social\Networks;

use App\Monitoring\Http;
use App\Social\SocialPost;
use App\Support\SocialSettings;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * YouTube: recent videos with the hashtag (YouTube Data API v3 search).
 * A search costs 100 of the 10,000 daily quota units, hence every three hours.
 */
class YouTube implements Network
{
    public function __construct(private SocialSettings $settings) {}

    public function key(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    public function configured(): bool
    {
        return filled($this->settings->get('youtube')['api_key'] ?? null);
    }

    public function interval(): int
    {
        return 180;
    }

    public function fetch(string $tag): array
    {
        $response = Http::client()->get('https://www.googleapis.com/youtube/v3/search', [
            'part' => 'snippet',
            'q' => '#'.$tag,
            'type' => 'video',
            'order' => 'date',
            'maxResults' => 25,
            'publishedAfter' => now()->subWeek()->utc()->format('Y-m-d\TH:i:s\Z'),
            'key' => $this->settings->get('youtube')['api_key'] ?? '',
        ]);

        if (! $response->successful()) {
            // The error message, never the URL: it carries the key.
            throw new RuntimeException("YouTube: HTTP {$response->status()} ".($response->json('error.message') ?? ''));
        }

        /** @var array<int, array<string, mixed>> $items */
        $items = $response->json('items', []);

        return collect($items)
            ->filter(fn (array $item) => isset($item['id']['videoId']))
            ->map(fn (array $item) => new SocialPost(
                url: 'https://www.youtube.com/watch?v='.$item['id']['videoId'],
                text: trim(html_entity_decode(($item['snippet']['title'] ?? '')."\n".($item['snippet']['description'] ?? ''), ENT_QUOTES | ENT_HTML5)),
                author: $item['snippet']['channelTitle'] ?? null,
                publishedAt: isset($item['snippet']['publishedAt']) ? CarbonImmutable::parse($item['snippet']['publishedAt']) : null,
            ))->values()->all();
    }
}
