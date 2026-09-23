<?php

namespace App\Monitoring;

use Illuminate\Support\Facades\Cache;

/**
 * Minimal robots.txt check for the scraper (rules for "*" and CIDASH), cached for a day.
 */
class Robots
{
    public function allows(string $url): bool
    {
        $parts = parse_url($url);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        $path = $parts['path'] ?? '/';

        $disallowed = Cache::remember('robots:'.$origin, now()->addDay(), function () use ($origin) {
            $response = Http::client()->get($origin.'/robots.txt');

            return $response->successful() ? self::disallowedPaths($response->body()) : [];
        });

        foreach ($disallowed as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public static function disallowedPaths(string $robots): array
    {
        $applies = false;
        $paths = [];

        foreach (preg_split('/\R/', $robots) ?: [] as $line) {
            $line = trim(preg_replace('/#.*/', '', $line) ?? '');
            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                $applies = $value === '*' || stripos($value, 'cidash') !== false;
            } elseif ($field === 'disallow' && $applies) {
                $paths[] = $value;
            }
        }

        return $paths;
    }
}
