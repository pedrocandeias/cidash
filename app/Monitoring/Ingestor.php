<?php

namespace App\Monitoring;

use App\Enums\SourceKind;
use App\Enums\TriageStatus;
use App\Models\NewsItem;
use App\Models\NewsItemState;
use App\Models\Source;
use App\Monitoring\Fetchers\GoogleNewsFetcher;
use App\Monitoring\Fetchers\RssFetcher;
use App\Monitoring\Fetchers\ScraperFetcher;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Fetches a source and stores new articles once for the whole instance, then
 * gives each subscribed workspace its own triage state.
 */
class Ingestor
{
    public function __construct(private Stories $stories, private WorkspaceContext $context) {}

    /**
     * @return int number of new articles
     */
    public function run(Source $source): int
    {
        try {
            $entries = $this->fetcher($source)->fetch($source);
        } catch (Throwable $e) {
            $source->forceFill([
                'last_fetched_at' => now(),
                'last_error' => mb_strimwidth($e->getMessage(), 0, 1000),
                'consecutive_failures' => $source->consecutive_failures + 1,
            ])->save();

            return 0;
        }

        $new = 0;
        foreach ($entries as $entry) {
            if ($this->store($source, $entry)) {
                $new++;
            }
        }

        $source->forceFill(['last_fetched_at' => now(), 'last_error' => null, 'consecutive_failures' => 0])->save();

        return $new;
    }

    private function store(Source $source, FeedEntry $entry): bool
    {
        $canonical = Urls::canonical($entry->url);
        $hash = Urls::hash($canonical);

        if (NewsItem::where('url_hash', $hash)->exists()) {
            return false;
        }

        DB::transaction(function () use ($source, $entry, $canonical, $hash) {
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
            $this->distribute($source, $item);
        });

        return true;
    }

    /**
     * Each workspace subscribed to the source gets the item in its triage inbox.
     * Runs for several workspaces, so the context is switched for each one.
     */
    private function distribute(Source $source, NewsItem $item): void
    {
        $previous = $this->context->get();

        foreach ($source->workspaces()->get() as $workspace) {
            $this->context->set($workspace);

            NewsItemState::create([
                'workspace_id' => $workspace->id,
                'news_item_id' => $item->id,
                'headline' => $item->headline,
                'status' => TriageStatus::New,
            ]);
        }

        if ($previous !== null) {
            $this->context->set($previous);
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
