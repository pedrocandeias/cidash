<?php

namespace App\Social\Networks;

use App\Social\SocialPost;

interface Network
{
    public function key(): string;

    public function label(): string;

    /** Whether the credentials it needs are set. */
    public function configured(): bool;

    /** Minutes between two collections (quotas differ per network). */
    public function interval(): int;

    /**
     * Recent public posts with the hashtag (without "#").
     *
     * @return array<int, SocialPost>
     */
    public function fetch(string $tag): array;
}
