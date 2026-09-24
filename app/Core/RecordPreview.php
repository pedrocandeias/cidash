<?php

namespace App\Core;

use App\Models\CalendarEvent;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\Mention;
use App\Models\NewsItemState;
use App\Models\Notice;
use App\Models\Person;
use App\Models\PressRequest;
use App\Models\Record;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;

/**
 * What the side drawer shows for any record: its main fields, a short text,
 * status and tags, without leaving the current page (ARCHITECTURE.md §3).
 * Labels are English i18n keys; `kind` tells the client how to show the value.
 */
class RecordPreview
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Record $record): array
    {
        $subject = $record->subject;

        [$status, $fields, $body] = $subject instanceof Model ? self::details($subject) : [null, [], null];

        return [
            ...RecordTypes::summary($record),
            'status' => $status,
            'fields' => array_values(array_filter($fields, fn (array $field) => $field['value'] !== null && $field['value'] !== '')),
            'body' => $body !== null ? mb_strimwidth(strip_tags($body), 0, 600, '…') : null,
            'tags' => $record->tags()->pluck('name')->all(),
            'counts' => [
                'comments' => $record->comments()->count(),
                'attachments' => $record->attachments()->count(),
            ],
        ];
    }

    /**
     * @return array{0: array{value: string, list: string}|null, 1: array<int, array{label: string, value: mixed, kind: string}>, 2: string|null}
     */
    private static function details(Model $subject): array
    {
        $field = fn (string $label, mixed $value, string $kind = 'text') => ['label' => $label, 'value' => $value, 'kind' => $kind];
        $status = fn (\BackedEnum $value, string $list) => ['value' => (string) $value->value, 'list' => $list];

        return match (true) {
            $subject instanceof Task => [$status($subject->status, 'task'), [
                $field('Task type', $subject->type, 'task_type'),
                $field('Deadline', $subject->deadline?->toDateString(), 'date'),
                $field('Assignees', $subject->assigneeNames()),
                $field('Priority', $subject->priority->value, 'priority'),
            ], $subject->description],
            $subject instanceof CalendarEvent => [$status($subject->status, 'event'), [
                $field('Start', $subject->all_day ? $subject->start_at->toDateString() : $subject->start_at->toIso8601String(), $subject->all_day ? 'date' : 'datetime'),
                $field('Type', $subject->type, 'event_type'),
                $field('Location', $subject->location),
                $field('People responsible', $subject->assigneeNames()),
            ], $subject->description],
            $subject instanceof Notice => [null, [
                $field('Publish on', $subject->published_at->toIso8601String(), 'datetime'),
                $field('Expires on', $subject->expires_at?->toIso8601String(), 'datetime'),
            ], $subject->body],
            $subject instanceof PressRequest => [$status($subject->status, 'press'), [
                $field('Journalist', $subject->journalist),
                $field('Media outlet', $subject->media_outlet),
                $field('Deadline', $subject->deadline?->toIso8601String(), 'datetime'),
                $field('People responsible', $subject->assigneeNames()),
            ], $subject->request],
            $subject instanceof ContentItem => [$status($subject->stage, 'content'), [
                $field('Format', $subject->format, 'content_format'),
                $field('People responsible', $subject->assigneeNames()),
                $field('Publication date', $subject->publish_at?->toIso8601String(), 'datetime'),
            ], $subject->brief],
            $subject instanceof Campaign => [$status($subject->status, 'campaign'), [
                $field('Start', $subject->start_date?->toDateString(), 'date'),
                $field('End', $subject->end_date?->toDateString(), 'date'),
            ], $subject->description],
            $subject instanceof Person => [null, [
                $field('Academic title', $subject->academic_title),
                $field('Affiliation', $subject->affiliation),
            ], $subject->short_bio],
            $subject instanceof NewsItemState => [$status($subject->status, 'triage'), [
                $field('Media outlet', $subject->newsItem->outlet),
                $field('Published', ($subject->newsItem->published_at ?? $subject->newsItem->retrieved_at)->toIso8601String(), 'datetime'),
            ], $subject->newsItem->summary],
            $subject instanceof Mention => [$status($subject->review_status, 'triage'), [
                $field('Media outlet', $subject->outlet),
                $field('Published', $subject->published_at?->toIso8601String(), 'datetime'),
                $field('Matched term', $subject->matched_keyword),
            ], $subject->excerpt],
            default => [null, [], null],
        };
    }
}
