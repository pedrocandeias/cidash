<?php

namespace App\Core;

use App\Models\CalendarEvent;
use App\Models\Record;
use App\Models\Task;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The record types known to the core: morph alias (stored in objects.type),
 * model, detail route and label. A new module adds one line here.
 */
class RecordTypes
{
    /**
     * @var array<string, array{model: class-string, route: string, label: string}>
     */
    private const TYPES = [
        'task' => ['model' => Task::class, 'route' => 'tasks.show', 'label' => 'Task'],
        'event' => ['model' => CalendarEvent::class, 'route' => 'events.show', 'label' => 'Event'],
    ];

    public static function register(): void
    {
        Relation::morphMap(array_map(fn (array $type) => $type['model'], self::TYPES));
    }

    public static function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? $type;
    }

    public static function url(Record $record): ?string
    {
        $route = self::TYPES[$record->type]['route'] ?? null;

        return $route !== null ? route($route, $record->id, absolute: false) : null;
    }

    /**
     * Summary used wherever a record is shown by reference (relations, search).
     *
     * @return array{id: string, type: string, label: string, title: string, url: ?string}
     */
    public static function summary(Record $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->type,
            'label' => self::label($record->type),
            'title' => $record->title,
            'url' => self::url($record),
        ];
    }
}
