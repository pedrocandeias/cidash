<?php

namespace App\Social\Networks;

use App\Models\SocialNetworkState;
use App\Monitoring\Http;
use App\Social\SocialPost;
use App\Support\SocialSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Instagram: recent public media with the hashtag (Instagram Graph API hashtag search).
 * Needs an Instagram Business account linked to a Facebook page and a Meta app token.
 * Meta allows 30 different hashtags per 7 days and does not return the author.
 */
class Instagram implements Network
{
    private const GRAPH = 'https://graph.facebook.com/v21.0';

    public function __construct(private SocialSettings $settings) {}

    public function key(): string
    {
        return 'instagram';
    }

    public function label(): string
    {
        return 'Instagram';
    }

    public function configured(): bool
    {
        $credentials = $this->settings->get('instagram');

        return filled($credentials['account_id'] ?? null) && filled($credentials['access_token'] ?? null);
    }

    public function interval(): int
    {
        return 30;
    }

    public function fetch(string $tag): array
    {
        $credentials = $this->settings->get('instagram');
        $query = ['user_id' => $credentials['account_id'] ?? '', 'access_token' => $credentials['access_token'] ?? ''];

        $response = Http::client()->get(self::GRAPH.'/'.$this->hashtagId($tag, $query).'/recent_media', [
            ...$query,
            'fields' => 'id,caption,permalink,timestamp',
            'limit' => 50,
        ]);
        $this->guard($response);

        /** @var array<int, array<string, mixed>> $media */
        $media = $response->json('data', []);

        return collect($media)->map(fn (array $media) => new SocialPost(
            url: $media['permalink'],
            text: trim((string) ($media['caption'] ?? '')),
            author: null,
            publishedAt: isset($media['timestamp']) ? CarbonImmutable::parse($media['timestamp']) : null,
        ))->all();
    }

    /**
     * Hashtag ids are looked up once and kept: each lookup counts towards the 30 per week.
     *
     * @param  array<string, string>  $query
     */
    private function hashtagId(string $tag, array $query): string
    {
        $state = SocialNetworkState::for('instagram');
        $ids = $state->state['hashtag_ids'] ?? [];

        if (! isset($ids[$tag])) {
            $response = Http::client()->get(self::GRAPH.'/ig_hashtag_search', [...$query, 'q' => $tag]);
            $this->guard($response);
            $ids[$tag] = (string) ($response->json('data.0.id') ?? throw new RuntimeException("Instagram: #{$tag} not found"));
            $state->forceFill(['state' => [...($state->state ?? []), 'hashtag_ids' => $ids]])->save();
        }

        return $ids[$tag];
    }

    private function guard(Response $response): void
    {
        if (! $response->successful()) {
            // The error message, never the URL: it carries the token.
            throw new RuntimeException("Instagram: HTTP {$response->status()} ".($response->json('error.message') ?? ''));
        }
    }
}
