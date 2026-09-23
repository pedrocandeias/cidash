<?php

namespace App\Monitoring;

use Carbon\CarbonInterface;

/**
 * Deterministic relevance of a story for a team, from 0 to 10, with the
 * reasons behind it. No AI: it only uses what the team configured.
 */
class Relevance
{
    /**
     * @return array{score: int, reasons: array<int, string>}
     */
    public static function score(bool $matchesRule, bool $aboutPerson, bool $prioritySource, int $outlets, CarbonInterface $publishedAt): array
    {
        $score = 0;
        $reasons = [];

        if ($matchesRule) {
            $score += 4;
            $reasons[] = 'Matches a monitoring rule';
        }
        if ($aboutPerson) {
            $score += 2;
            $reasons[] = 'About a person of interest';
        }
        if ($prioritySource) {
            $score += 2;
            $reasons[] = 'Priority source';
        }
        if ($outlets > 1) {
            $score += min($outlets - 1, 3);
            $reasons[] = 'Covered by several outlets';
        }
        // Older stories lose weight; the inbox is about what is happening now.
        if ($publishedAt->lt(now()->subDays(2))) {
            $score -= 1;
        }

        return ['score' => max(0, min(10, $score)), 'reasons' => $reasons];
    }
}
