<?php

use App\Core\Terms;
use App\Support\Options;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Teams that already have their task types get the new list: the new types are
     * added in order, the old ones that are not in it are switched off (never deleted,
     * since tasks keep their key). Teams without a list get the new defaults on first use.
     */
    public function up(): void
    {
        $types = Options::DEFAULTS['task_type'];

        foreach (DB::table('workspace_options')->where('list', 'task_type')->distinct()->pluck('workspace_id') as $workspaceId) {
            $existing = DB::table('workspace_options')->where('workspace_id', $workspaceId)->where('list', 'task_type')->get();
            $position = 0;
            $kept = [];

            foreach ($types as $key => [$label, $color]) {
                // A type the team already has, by key or by name.
                $row = $existing->firstWhere('key', $key) ?? $existing->firstWhere('normalized_label', Terms::normalize($label));
                if ($row !== null) {
                    DB::table('workspace_options')->where('id', $row->id)->update(['active' => true, 'position' => $position++]);
                    $kept[] = $row->id;

                    continue;
                }

                DB::table('workspace_options')->insert([
                    'workspace_id' => $workspaceId, 'list' => 'task_type', 'key' => $key, 'label' => $label,
                    'normalized_label' => Terms::normalize($label), 'color' => $color, 'position' => $position++,
                    'active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            foreach ($existing->whereNotIn('id', $kept) as $row) {
                DB::table('workspace_options')->where('id', $row->id)->update(['active' => false, 'position' => $position++]);
            }
        }
    }

    public function down(): void
    {
        // The new types stay: tasks may already use them.
    }
};
