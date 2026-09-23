<?php

namespace App\Monitoring\Fetchers;

use App\Models\Source;
use App\Monitoring\FeedEntry;

/**
 * Google News search feeds. Titles come as "Headline - Outlet"; the outlet is
 * split off so the same article from a direct feed groups in the same story.
 */
class GoogleNewsFetcher extends RssFetcher
{
    public function fetch(Source $source): array
    {
        return array_map(function (FeedEntry $entry) {
            if ($entry->outlet !== null && str_ends_with($entry->headline, ' - '.$entry->outlet)) {
                $entry->headline = mb_substr($entry->headline, 0, -mb_strlen(' - '.$entry->outlet));
            }

            // The description only repeats the headline and outlet as links.
            $entry->summary = null;

            return $entry;
        }, parent::fetch($source));
    }
}
