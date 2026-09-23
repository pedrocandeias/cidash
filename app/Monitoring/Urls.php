<?php

namespace App\Monitoring;

/**
 * Canonical URLs, so the same article reached through different links
 * (tracking parameters, fragments, trailing slashes) is stored once.
 */
class Urls
{
    private const TRACKING = ['fbclid', 'gclid', 'mc_cid', 'mc_eid', 'ref', 'cmpid', 'ocid'];

    public static function canonical(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['host'])) {
            return trim($url);
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter(
            $query,
            fn ($key) => ! str_starts_with((string) $key, 'utm_') && ! in_array($key, self::TRACKING, true),
            ARRAY_FILTER_USE_KEY,
        );
        ksort($query);

        $host = strtolower($parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = rtrim($parts['path'] ?? '', '/');

        return 'https://'.$host.$path.($query !== [] ? '?'.http_build_query($query) : '');
    }

    public static function hash(string $canonical): string
    {
        return hash('sha256', $canonical);
    }
}
