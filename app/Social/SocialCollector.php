<?php

namespace App\Social;

use App\Core\Scopes\WorkspaceScope;
use App\Enums\TriageStatus;
use App\Models\Mention;
use App\Models\SocialHashtag;
use App\Models\SocialNetworkState;
use App\Models\Workspace;
use App\Monitoring\Urls;
use App\Social\Networks\Bluesky;
use App\Social\Networks\Instagram;
use App\Social\Networks\Mastodon;
use App\Social\Networks\Network;
use App\Social\Networks\YouTube;
use App\Support\WorkspaceContext;
use Throwable;

/**
 * Collects public posts with the hashtags the teams follow and files each one
 * as a mention of every team that follows one of its hashtags.
 */
class SocialCollector
{
    /** @var array<int, class-string<Network>> */
    private const NETWORKS = [Mastodon::class, Bluesky::class, YouTube::class, Instagram::class];

    public function __construct(private WorkspaceContext $context) {}

    /**
     * @return array<string, Network>
     */
    public function networks(): array
    {
        $networks = [];
        foreach (self::NETWORKS as $class) {
            $network = app($class);
            $networks[$network->key()] = $network;
        }

        return $networks;
    }

    /**
     * Runs every configured network that is due (or all of them with $force).
     *
     * @return array<string, int|string> network => new mentions, or the error
     */
    public function run(bool $force = false): array
    {
        $tags = $this->followedTags();
        $results = [];

        foreach ($this->networks() as $key => $network) {
            $state = SocialNetworkState::for($key);
            if (! $network->configured() || $tags === [] || (! $force && $state->last_fetched_at?->addMinutes($network->interval())->isFuture())) {
                continue;
            }

            try {
                $new = 0;
                foreach ($tags as $tag) {
                    foreach ($network->fetch($tag) as $post) {
                        $new += $this->distribute($network, $tag, $post);
                    }
                }
                $state->forceFill(['last_fetched_at' => now(), 'last_error' => null, 'consecutive_failures' => 0])->save();
                $results[$key] = $new;
            } catch (Throwable $e) {
                $state->forceFill([
                    'last_fetched_at' => now(),
                    // Query strings can carry API keys and tokens.
                    'last_error' => mb_strimwidth((string) preg_replace('/\?\S*/', '?…', $e->getMessage()), 0, 1000),
                    'consecutive_failures' => $state->consecutive_failures + 1,
                ])->save();
                $results[$key] = $state->last_error;
            }
        }

        return $results;
    }

    /**
     * Hashtags followed by at least one active team.
     *
     * @return array<int, string>
     */
    public function followedTags(): array
    {
        return SocialHashtag::withoutGlobalScope(WorkspaceScope::class)
            ->whereHas('workspace', fn ($query) => $query->whereNull('archived_at'))
            ->distinct()
            ->orderBy('tag')
            ->pluck('tag')
            ->all();
    }

    /**
     * @return int new mentions created
     */
    private function distribute(Network $network, string $tag, SocialPost $post): int
    {
        if ($post->url === '' || $post->text === '') {
            return 0;
        }

        $hash = Urls::hash(Urls::canonical($post->url));
        $workspaceIds = SocialHashtag::withoutGlobalScope(WorkspaceScope::class)->where('tag', $tag)->pluck('workspace_id');
        $new = 0;

        foreach (Workspace::active()->whereKey($workspaceIds)->get() as $workspace) {
            $new += $this->context->within($workspace, function () use ($workspace, $network, $tag, $post, $hash) {
                // The same post under two of the team's hashtags is one mention.
                if (Mention::where('url_hash', $hash)->exists()) {
                    return 0;
                }

                Mention::create([
                    'workspace_id' => $workspace->id,
                    'url' => $post->url,
                    'url_hash' => $hash,
                    'headline' => mb_strimwidth((string) preg_replace('/\s+/', ' ', $post->text), 0, 250, '…'),
                    'excerpt' => mb_strimwidth($post->text, 0, 2000, '…'),
                    'outlet' => $network->label(),
                    'author' => $post->author,
                    'network' => $network->key(),
                    'published_at' => $post->publishedAt,
                    'matched_keyword' => '#'.$tag,
                    'review_status' => TriageStatus::New,
                ]);

                return 1;
            });
        }

        return $new;
    }
}
