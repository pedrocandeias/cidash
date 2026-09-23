<?php

namespace App\Monitoring\Fetchers;

use App\Models\Source;
use App\Monitoring\FeedParser;
use App\Monitoring\Http;
use RuntimeException;

/**
 * Feeds published for syndication (RSS/Atom). The outlet is the source name.
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
            $entry->outlet ??= $source->name;

            return $entry;
        }, $this->parser->parse($response->body()));
    }
}
