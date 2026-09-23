<?php

namespace App\Monitoring;

use Carbon\CarbonImmutable;

/**
 * One article as found by a fetcher, before it is stored.
 */
final class FeedEntry
{
    public function __construct(
        public string $headline,
        public string $url,
        public ?string $summary = null,
        public ?CarbonImmutable $publishedAt = null,
        public ?string $outlet = null,
    ) {}
}
