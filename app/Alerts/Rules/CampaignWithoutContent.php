<?php

namespace App\Alerts\Rules;

use App\Alerts\Finding;
use App\Alerts\RuleType;
use App\Enums\AlertSeverity;
use App\Enums\CampaignStatus;
use App\Enums\RelationType;
use App\Models\AlertRule;
use App\Models\Campaign;
use App\Models\Link;
use App\Models\Workspace;

class CampaignWithoutContent implements RuleType
{
    public function key(): string
    {
        return 'campaign_without_content';
    }

    public function message(): string
    {
        return 'Campaign without content';
    }

    public function description(): string
    {
        return 'A campaign starts within :days_before days (or has started) and has no content.';
    }

    public function severity(): AlertSeverity
    {
        return AlertSeverity::Warning;
    }

    public function parameters(): array
    {
        return ['days_before' => 7];
    }

    public function evaluate(AlertRule $rule, Workspace $workspace): iterable
    {
        $campaigns = Campaign::query()
            ->with('responsibles:id')
            ->whereIn('status', [CampaignStatus::Planning, CampaignStatus::Active])
            ->whereNotNull('start_date')
            ->where('start_date', '<=', now()->addDays((int) $rule->param('days_before'))->toDateString())
            ->get();

        $withContent = Link::whereIn('target_id', $campaigns->modelKeys())
            ->where('type', RelationType::PartOf)
            ->whereHas('source', fn ($query) => $query->where('type', 'content'))
            ->pluck('target_id')
            ->all();

        foreach ($campaigns as $campaign) {
            if (! in_array($campaign->id, $withContent, true)) {
                yield new Finding("campaign_without_content:{$campaign->id}", $campaign->name, $campaign->id, $campaign->start_date, $campaign->responsibles->modelKeys());
            }
        }
    }
}
