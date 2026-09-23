<?php

namespace App\Alerts\Rules;

use App\Alerts\Finding;
use App\Alerts\RuleType;
use App\Enums\AlertSeverity;
use App\Enums\ContentStage;
use App\Models\AlertRule;
use App\Models\ContentItem;
use App\Models\Workspace;

class ContentStuck implements RuleType
{
    public function key(): string
    {
        return 'content_stuck';
    }

    public function message(): string
    {
        return 'Content stuck in review';
    }

    public function description(): string
    {
        return 'A content item has been in review for :days days or more.';
    }

    public function severity(): AlertSeverity
    {
        return AlertSeverity::Warning;
    }

    public function parameters(): array
    {
        return ['days' => 3];
    }

    public function evaluate(AlertRule $rule, Workspace $workspace): iterable
    {
        $items = ContentItem::query()
            ->where('stage', ContentStage::Review)
            ->where('stage_changed_at', '<=', now()->subDays((int) $rule->param('days')))
            ->get();

        foreach ($items as $item) {
            $owners = $item->owner_id !== null ? [$item->owner_id] : [];

            yield new Finding("content_stuck:{$item->id}", $item->title, $item->id, null, $owners);
        }
    }
}
