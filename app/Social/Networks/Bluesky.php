<?php

namespace App\Social\Networks;

use App\Monitoring\Http;
use App\Social\SocialPost;
use App\Support\SocialSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Bluesky: post search by hashtag. Search needs a signed-in account, so it uses
 * an account handle and an app password (Bluesky → Settings → App passwords).
 */
class Bluesky implements Network
{
    private const SERVICE = 'https://bsky.social/xrpc';

    public function __construct(private SocialSettings $settings) {}

    public function key(): string
    {
        return 'bluesky';
    }

    public function label(): string
    {
        return 'Bluesky';
    }

    public function configured(): bool
    {
        $credentials = $this->settings->get('bluesky');

        return filled($credentials['handle'] ?? null) && filled($credentials['app_password'] ?? null);
    }

    public function interval(): int
    {
        return 15;
    }

    public function fetch(string $tag): array
    {
        $response = Http::client()->withToken($this->token())
            ->get(self::SERVICE.'/app.bsky.feed.searchPosts', ['q' => '#'.$tag, 'sort' => 'latest', 'limit' => 50]);

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey());
        }
        if (! $response->successful()) {
            throw new RuntimeException("Bluesky: HTTP {$response->status()} ".($response->json('message') ?? ''));
        }

        /** @var array<int, array<string, mixed>> $posts */
        $posts = $response->json('posts', []);

        return collect($posts)->map(function (array $post) {
            $handle = $post['author']['handle'] ?? '';
            $rkey = basename((string) ($post['uri'] ?? ''));

            return new SocialPost(
                url: "https://bsky.app/profile/{$handle}/post/{$rkey}",
                text: trim((string) ($post['record']['text'] ?? '')),
                author: $handle !== '' ? '@'.$handle : null,
                publishedAt: isset($post['record']['createdAt']) ? CarbonImmutable::parse($post['record']['createdAt']) : null,
            );
        })->all();
    }

    /**
     * A session token, kept for an hour (Bluesky's access tokens last about two).
     */
    private function token(): string
    {
        return Cache::remember($this->cacheKey(), 3600, function () {
            $credentials = $this->settings->get('bluesky');
            $response = Http::client()->post(self::SERVICE.'/com.atproto.server.createSession', [
                'identifier' => $credentials['handle'] ?? '',
                'password' => $credentials['app_password'] ?? '',
            ]);

            if (! $response->successful()) {
                throw new RuntimeException("Bluesky: sign-in failed (HTTP {$response->status()}) ".($response->json('message') ?? ''));
            }

            return (string) $response->json('accessJwt');
        });
    }

    private function cacheKey(): string
    {
        return 'social:bluesky:token';
    }
}
