<?php

namespace App\Monitoring\Fetchers;

use App\Models\Source;
use App\Monitoring\FeedParser;
use App\Monitoring\Http;
use RuntimeException;

/**
 * Feeds published for syndication (RSS/Atom). The outlet is the source name, or
 * for aggregators the original outlet named in each item.
 */
class RssFetcher implements Fetcher
{
    public function __construct(protected FeedParser $parser) {}

    public function fetch(Source $source): array
    {
        $response = Http::client()->get($source->url);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()} from {$source->url}");
        }

        return array_map(function ($entry) use ($source) {
            // Aggregators (SAPO Notícias) name the original outlet as "Outlet/Journalist".
            if (($source->config['outlet_from_author'] ?? false) && filled($entry->author)) {
                $entry->outlet ??= trim(explode('/', $entry->author)[0]) ?: null;
            }
            $entry->outlet ??= $source->name;

            return $entry;
        }, $this->parser->parse($response->body()));
    }
}
