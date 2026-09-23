<?php

namespace App\Monitoring;

use App\Core\Terms;
use App\Models\NewsItem;
use App\Models\Story;

/**
 * Groups articles about the same happening: a new item joins the story of a
 * recent item (72 h) whose headline is similar enough (trigram overlap).
 */
class Stories
{
    private const THRESHOLD = 0.5;

    public function assign(NewsItem $item): void
    {
        $candidates = NewsItem::whereNotNull('story_id')
            ->where('retrieved_at', '>=', now()->subHours(72))
            ->whereKeyNot($item->id)
            ->latest('id')
            ->limit(1000)
            ->get(['id', 'headline', 'story_id']);

        $best = null;
        $bestScore = 0.0;
        foreach ($candidates as $candidate) {
            $score = self::similarity($item->headline, $candidate->headline);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$candidate, $score];
            }
        }

        if ($best !== null && $bestScore >= self::THRESHOLD) {
            $story = Story::findOrFail($best->story_id);
            $story->forceFill(['last_seen_at' => now(), 'item_count' => $story->item_count + 1])->save();
        } else {
            $story = Story::create(['title' => $item->headline, 'first_seen_at' => now(), 'last_seen_at' => now(), 'item_count' => 1]);
        }

        $item->forceFill(['story_id' => $story->id])->save();
    }

    /**
     * Jaccard similarity of the character trigrams of two normalized headlines.
     */
    public static function similarity(string $a, string $b): float
    {
        $trigrams = function (string $text): array {
            $text = ' '.Terms::normalize($text).' ';
            $set = [];
            for ($i = 0; $i < mb_strlen($text) - 2; $i++) {
                $set[mb_substr($text, $i, 3)] = true;
            }

            return $set;
        };

        $x = $trigrams($a);
        $y = $trigrams($b);
        $union = count($x + $y);

        return $union === 0 ? 0.0 : count(array_intersect_key($x, $y)) / $union;
    }
}
