<?php

namespace App\Social;

use Carbon\CarbonImmutable;

/**
 * One public post found under a hashtag.
 */
final readonly class SocialPost
{
    public function __construct(
        public string $url,
        public string $text,
        public ?string $author,
        public ?CarbonImmutable $publishedAt,
    ) {}
}
