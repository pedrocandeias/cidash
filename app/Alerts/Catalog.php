<?php

namespace App\Alerts;

use InvalidArgumentException;

/**
 * The alert rule types teams can switch on and tune (no generic rule builder).
 */
final class Catalog
{
    /** @var array<int, class-string<RuleType>> */
    private const TYPES = [
        Rules\EventWithoutOwner::class,
        Rules\PressDeadlineNear::class,
        Rules\ContentStuck::class,
        Rules\CampaignWithoutContent::class,
        Rules\PrioritySourceItem::class,
        Rules\SourceFailing::class,
        Rules\MentionSpike::class,
    ];

    /**
     * @return array<string, RuleType>
     */
    public static function all(): array
    {
        $types = [];
        foreach (self::TYPES as $class) {
            $type = app($class);
            $types[$type->key()] = $type;
        }

        return $types;
    }

    public static function get(string $key): RuleType
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Unknown alert rule type [{$key}].");
    }
}
