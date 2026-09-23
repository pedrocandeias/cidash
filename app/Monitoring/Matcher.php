<?php

namespace App\Monitoring;

use App\Core\Scopes\WorkspaceScope;
use App\Models\MonitoringRule;
use Illuminate\Support\Collection;

/**
 * Finds the first active monitoring rule of a workspace that matches a text.
 * Runs during ingestion, across workspaces, so rules are loaded explicitly per
 * workspace (without the current-workspace scope) and cached for the run.
 */
class Matcher
{
    /** @var array<int, Collection<int, MonitoringRule>> */
    private array $rules = [];

    /**
     * @return array{rule: MonitoringRule, term: string}|null
     */
    public function first(int $workspaceId, string $text): ?array
    {
        foreach ($this->rules($workspaceId) as $rule) {
            if (($term = $rule->match($text)) !== null) {
                return ['rule' => $rule, 'term' => $term];
            }
        }

        return null;
    }

    public function hasRules(int $workspaceId): bool
    {
        return $this->rules($workspaceId)->isNotEmpty();
    }

    /**
     * @return Collection<int, MonitoringRule>
     */
    private function rules(int $workspaceId): Collection
    {
        return $this->rules[$workspaceId] ??= MonitoringRule::withoutGlobalScope(WorkspaceScope::class)
            ->where('workspace_id', $workspaceId)
            ->where('active', true)
            ->get();
    }
}
