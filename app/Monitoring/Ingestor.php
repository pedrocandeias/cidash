<?php

namespace App\Monitoring;

use App\Alerts\Rules\SourceFailing;
use App\Core\Links;
use App\Enums\RelationType;
use App\Enums\SourceKind;
use App\Enums\TriageStatus;
use App\Models\Mention;
use App\Models\MonitoringRule;
use App\Models\NewsItem;
use App\Models\NewsItemState;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use App\Monitoring\Fetchers\GoogleNewsFetcher;
use App\Monitoring\Fetchers\RssFetcher;
use App\Monitoring\Fetchers\ScraperFetcher;
use App\Notifications\SourceFailingNotification;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fetches a source and stores new articles once for the whole instance. Each
 * subscribed workspace gets a triage state (only for articles matching its
 * rules when the subscription says so) and a mention when a rule matches.
 */
class Ingestor
{
    public function __construct(
        private Stories $stories,
        private WorkspaceContext $context,
        private Matcher $matcher,
        private Links $links,
    ) {}

    /**
     * @return int number of new articles
     */
    public function run(Source $source): int
    {
        try {
            $entries = $this->fetcher($source)->fetch($source);
        } catch (Throwable $e) {
            $this->fail($source, $e);

            return 0;
        }

        $new = 0;
        foreach ($entries as $entry) {
            if (($item = $this->store($source, $entry)) !== null) {
                $this->distribute($source, $item);
                $new++;
            }
        }

        $source->forceFill(['last_fetched_at' => now(), 'last_error' => null, 'consecutive_failures' => 0])->save();

        return $new;
    }

    /**
     * Google News search for one rule; its matches become mentions of the rule's workspace only.
     *
     * @return int number of new mentions
     */
    public function runRule(MonitoringRule $rule, Source $systemSource): int
    {
        $source = $systemSource->replicate()->forceFill([
            'url' => 'https://news.google.com/rss/search?'.http_build_query(['q' => $rule->googleNewsQuery(), 'hl' => 'pt-PT', 'gl' => 'PT', 'ceid' => 'PT:pt-150']),
        ]);

        try {
            $entries = app(GoogleNewsFetcher::class)->fetch($source);
        } catch (Throwable $e) {
            $this->fail($systemSource, $e);

            return 0;
        }

        $workspace = Workspace::findOrFail($rule->workspace_id);
        $new = 0;
        foreach ($entries as $entry) {
            $item = $this->store($systemSource, $entry) ?? NewsItem::where('url_hash', Urls::hash(Urls::canonical($entry->url)))->first();

            if ($item === null) {
                continue;
            }

            // Google News already searched for the terms; the match only tells which one.
            $term = $rule->match($item->headline.' '.$item->summary) ?? $rule->terms()[0] ?? $rule->name;
            if ($this->within($workspace, fn () => $this->mention($item, $rule, $term))) {
                $new++;
            }
        }

        $rule->forceFill(['last_fetched_at' => now()])->save();
        $systemSource->forceFill(['last_fetched_at' => now(), 'last_error' => null, 'consecutive_failures' => 0])->save();

        return $new;
    }

    private function store(Source $source, FeedEntry $entry): ?NewsItem
    {
        $canonical = Urls::canonical($entry->url);
        $hash = Urls::hash($canonical);

        if (NewsItem::where('url_hash', $hash)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($source, $entry, $canonical, $hash) {
            $item = NewsItem::create([
                'source_id' => $source->id,
                'url' => $entry->url,
                'canonical_url' => $canonical,
                'url_hash' => $hash,
                'headline' => mb_strimwidth($entry->headline, 0, 250, '…'),
                'summary' => $entry->summary,
                'outlet' => $entry->outlet,
                'published_at' => $entry->publishedAt,
                'retrieved_at' => now(),
                'language' => $source->config['language'] ?? null,
            ]);

            $this->stories->assign($item);

            return $item;
        });
    }

    private function distribute(Source $source, NewsItem $item): void
    {
        $text = $item->headline.' '.$item->summary;

        foreach ($source->workspaces()->whereNull('archived_at')->get() as $workspace) {
            $match = $this->matcher->first($workspace->id, $text);
            // A team without rules yet gets everything, so the inbox is never silently empty.
            $onlyMatching = (bool) $workspace->getRelationValue('pivot')?->only_matching && $this->matcher->hasRules($workspace->id);

            $this->within($workspace, function () use ($workspace, $item, $match, $onlyMatching) {
                if ($match === null && $onlyMatching) {
                    return;
                }

                NewsItemState::create([
                    'workspace_id' => $workspace->id,
                    'news_item_id' => $item->id,
                    'headline' => $item->headline,
                    'status' => TriageStatus::New,
                ]);

                if ($match !== null) {
                    $this->mention($item, $match['rule'], $match['term']);
                }
            });
        }
    }

    /**
     * @return bool whether a new mention was created
     */
    private function mention(NewsItem $item, MonitoringRule $rule, string $term): bool
    {
        if (Mention::where('url_hash', $item->url_hash)->exists()) {
            return false;
        }

        $mention = Mention::create([
            'workspace_id' => $rule->workspace_id,
            'news_item_id' => $item->id,
            'rule_id' => $rule->id,
            'url' => $item->url,
            'url_hash' => $item->url_hash,
            'headline' => $item->headline,
            'excerpt' => $item->summary,
            'outlet' => $item->outlet,
            'published_at' => $item->published_at,
            'matched_keyword' => $term,
            'category' => $rule->category,
            'review_status' => TriageStatus::New,
        ]);

        if ($rule->person_id !== null && ($person = Person::find($rule->person_id)) !== null) {
            $this->links->link($mention, $person, RelationType::Mentions);
        }

        return true;
    }

    /**
     * Runs $callback with $workspace as the current workspace (ingestion spans workspaces).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function within(Workspace $workspace, callable $callback): mixed
    {
        $previous = $this->context->get();
        $this->context->set($workspace);

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                $this->context->set($previous);
            }
        }
    }

    private function fail(Source $source, Throwable $e): void
    {
        $source->forceFill([
            'last_fetched_at' => now(),
            'last_error' => mb_strimwidth($e->getMessage(), 0, 1000),
            'consecutive_failures' => $source->consecutive_failures + 1,
        ])->save();

        // Super admins maintain the catalogue: tell them once, when a source starts failing.
        if ($source->consecutive_failures === SourceFailing::DEFAULT_FAILURES) {
            User::where('is_super_admin', true)->whereNull('deactivated_at')->get()
                ->each(fn (User $admin) => $admin->notify(new SourceFailingNotification($source)));
        }
    }

    private function fetcher(Source $source): Fetchers\Fetcher
    {
        return match ($source->kind) {
            SourceKind::Rss => app(RssFetcher::class),
            SourceKind::GoogleNews => app(GoogleNewsFetcher::class),
            SourceKind::Scraper => app(ScraperFetcher::class),
        };
    }
}
