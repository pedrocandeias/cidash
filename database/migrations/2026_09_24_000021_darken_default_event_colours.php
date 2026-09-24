<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The identity darkened two default event colours so white text on them passes 4.5:1.
     * Only teams still using the old default change; a colour a manager chose stays.
     */
    public function up(): void
    {
        foreach (['publication' => ['#059669', '#047857'], 'ephemeris' => ['#d97706', '#b45309']] as $key => [$old, $new]) {
            DB::table('workspace_options')->where('list', 'event_type')->where('key', $key)->where('color', $old)->update(['color' => $new]);
        }
    }

    public function down(): void
    {
        foreach (['publication' => ['#059669', '#047857'], 'ephemeris' => ['#d97706', '#b45309']] as $key => [$old, $new]) {
            DB::table('workspace_options')->where('list', 'event_type')->where('key', $key)->where('color', $new)->update(['color' => $old]);
        }
    }
};
