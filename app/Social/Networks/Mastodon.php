<?php

namespace App\Social\Networks;

use App\Monitoring\Http;
use App\Social\SocialPost;
use App\Support\SocialSettings;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Mastodon: the public hashtag timeline of one instance (no account needed).
 */
class Mastodon implements Network
{
    public const DEFAULT_INSTANCE = 'https://mastodon.social';

    public function __construct(private SocialSettings $settings) {}

    public function key(): string
    {
        return 'mastodon';
    }

    public function label(): string
    {
        return 'Mastodon';
    }

    public function configured(): bool
    {
        return true;
    }

    public function interval(): int
    {
        return 15;
    }

    public function fetch(string $tag): array
    {
        $instance = rtrim($this->settings->get('mastodon')['instance'] ?? self::DEFAULT_INSTANCE, '/');
        $response = Http::client()->get("{$instance}/api/v1/timelines/tag/".rawurlencode($tag), ['limit' => 40]);

        if (! $response->successful()) {
            throw new RuntimeException("Mastodon: HTTP {$response->status()}");
        }

        /** @var array<int, array<string, mixed>> $statuses */
        $statuses = $response->json();

        return collect($statuses)->map(fn (array $status) => new SocialPost(
            url: $status['url'] ?? $status['uri'],
            text: trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</p>'], ["\n", "\n", "\n"], $status['content'] ?? '')), ENT_QUOTES | ENT_HTML5)),
            author: isset($status['account']['acct']) ? '@'.$status['account']['acct'] : null,
            publishedAt: isset($status['created_at']) ? CarbonImmutable::parse($status['created_at']) : null,
        ))->all();
    }
}
