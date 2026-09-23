<?php

namespace App\Core;

use Illuminate\Support\Str;

/**
 * Protects user-created vocabularies (tags, expertise areas) against human-error
 * duplicates: "Astronomia", " astronomia " and "ASTRONOMÍA" are the same term.
 */
class Terms
{
    public static function normalize(string $term): string
    {
        return Str::of($term)->ascii()->lower()->squish()->toString();
    }

    public static function clean(string $term): string
    {
        return Str::squish($term);
    }

    /**
     * Whether two different terms are close enough to be a probable typo.
     */
    public static function similar(string $a, string $b, int $maxDistance = 2): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        return $a !== $b && levenshtein($a, $b) <= $maxDistance;
    }
}
