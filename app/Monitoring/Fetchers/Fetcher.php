<?php

namespace App\Monitoring\Fetchers;

use App\Models\Source;
use App\Monitoring\FeedEntry;

interface Fetcher
{
    /**
     * @return array<int, FeedEntry>
     */
    public function fetch(Source $source): array;
}
