<?php

namespace App\Briefing;

use App\Enums\TriageStatus;
use App\Models\Briefing;
use App\Models\Mention;
use App\Models\NewsItemState;
use App\Models\Workspace;
use App\Monitoring\Relevance;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Shared by the deterministic briefings: stores the snapshot for a period and
 * builds its sections of linked items.
 */
abstract class Builder
{
    protected const ITEMS = 10;

    public function __construct(private WorkspaceContext $context) {}

    /**
     * Generates (or regenerates) the briefing covering $day.
     */
    abstract public function generate(Workspace $workspace, ?CarbonImmutable $day = null): Briefing;

    /**
     * @param  callable(): array<int, array<string, mixed>>  $sections
     */
    protected function store(Workspace $workspace, string $kind, CarbonImmutable $start, CarbonImmutable $end, callable $sections): Briefing
    {
        $previous = $this->context->get();
        $this->context->set($workspace);

        try {
            // Dates are stored as datetimes; compare with Carbon values, not date strings.
            $briefing = Briefing::where('kind', $kind)->where('period_start', $start)->first()
                ?? new Briefing(['workspace_id' => $workspace->id, 'kind' => $kind, 'period_start' => $start]);

            $briefing->fill(['period_end' => $end, 'generated_at' => now(), 'content' => $sections()])->save();

            return $briefing;
        } finally {
            if ($previous !== null) {
                $this->context->set($previous);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{key: string, title: string, count: int, items: array<int, array<string, mixed>>}
     */
    protected function section(string $key, string $title, array $items, ?int $count = null): array
    {
        return ['key' => $key, 'title' => $title, 'count' => $count ?? count($items), 'items' => array_slice($items, 0, self::ITEMS)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(string $title, ?string $url, ?string $at = null, ?string $detail = null, ?string $label = null, bool $flag = false): array
    {
        return compact('title', 'url', 'at', 'detail', 'label', 'flag');
    }

    /**
     * @return array<string, mixed>
     */
    protected function news(Workspace $workspace, CarbonImmutable $since): array
    {
        $states = NewsItemState::with('newsItem')
            ->whereIn('status', [TriageStatus::New, TriageStatus::Relevant])
            ->where('created_at', '>=', $since)
            ->get();
        $priority = DB::table('workspace_sources')->where('workspace_id', $workspace->id)->where('is_priority', true)->pluck('source_id')->all();

        $mentions = Mention::with('rule:id,person_id')
            ->whereIn('url_hash', $states->map(fn (NewsItemState $state) => $state->newsItem->url_hash))
            ->get()
            ->keyBy('url_hash');

        // One line per story; stories marked relevant first, then by relevance score.
        $lines = $states->groupBy(fn (NewsItemState $state) => $state->newsItem->story_id ?? 'item-'.$state->id)
            ->map(function ($group) use ($priority, $mentions) {
                $matched = $group->map(fn (NewsItemState $state) => $mentions->get($state->newsItem->url_hash))->filter();
                $isPriority = $group->contains(fn (NewsItemState $state) => in_array($state->newsItem->source_id, $priority, true));
                $item = $group->first()->newsItem;

                return [
                    'state' => $group->first(),
                    'size' => $group->count(),
                    'relevant' => $group->contains(fn (NewsItemState $state) => $state->status === TriageStatus::Relevant),
                    'priority' => $isPriority,
                    'score' => Relevance::score(
                        $matched->isNotEmpty(),
                        $matched->contains(fn (Mention $mention) => $mention->rule?->person_id !== null),
                        $isPriority,
                        $group->map(fn (NewsItemState $state) => $state->newsItem->outlet)->filter()->unique()->count(),
                        $item->published_at ?? $item->retrieved_at,
                    )['score'],
                ];
            })
            ->sortByDesc(fn (array $line) => [$line['relevant'], $line['score'], $line['size']])
            ->values();

        return $this->section('news', 'News coverage', $lines->map(fn (array $line) => $this->item(
            $line['state']->newsItem->headline,
            route('news.show', $line['state'], absolute: false),
            ($line['state']->newsItem->published_at ?? $line['state']->newsItem->retrieved_at)->toIso8601String(),
            collect([$line['state']->newsItem->outlet, $line['size'] > 1 ? "{$line['size']}×" : null])->filter()->implode(' · '),
            label: $line['relevant'] ? 'Relevant' : null,
            flag: $line['priority'],
        ))->all(), $lines->count());
    }

    /**
     * @return array<string, mixed>
     */
    protected function mentions(CarbonImmutable $since): array
    {
        $mentions = Mention::where('created_at', '>=', $since)
            ->where('review_status', '!=', TriageStatus::Irrelevant)
            ->orderByDesc('published_at')
            ->get();

        return $this->section('mentions', 'Media mentions', $mentions->map(fn (Mention $mention) => $this->item(
            $mention->headline,
            route('mentions.show', $mention, absolute: false),
            $mention->published_at?->toIso8601String(),
            collect([$mention->outlet, $mention->matched_keyword])->filter()->implode(' · '),
            label: $mention->review_status === TriageStatus::Relevant ? 'Relevant' : null,
        ))->all());
    }
}
