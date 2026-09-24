<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the work starts: the creation date unless someone changes it.
        Schema::table('tasks', function (Blueprint $table) {
            $table->date('start_date')->nullable();
        });
        foreach (DB::table('tasks')->join('objects', 'objects.id', '=', 'tasks.id')->get(['tasks.id', 'tasks.deadline', 'objects.created_at']) as $task) {
            DB::table('tasks')->where('id', $task->id)->update([
                'start_date' => substr((string) $task->created_at, 0, 10).' 00:00:00',
                // Deadlines now have a time; a deadline set as a day meant "by the end of that day".
                'deadline' => $task->deadline === null ? null : substr((string) $task->deadline, 0, 10).' 23:59:00',
            ]);
        }

        // lead: the person responsible; co: people who share the work (tasks).
        Schema::table('record_assignees', function (Blueprint $table) {
            $table->string('role')->default('lead');
        });

        // A task with several people keeps the first one assigned as the person responsible.
        $taskAssignees = DB::table('record_assignees')
            ->whereIn('object_id', DB::table('tasks')->select('id'))
            ->orderBy('created_at')
            ->orderBy('user_id')
            ->get()
            ->groupBy('object_id');
        foreach ($taskAssignees as $objectId => $rows) {
            foreach ($rows->slice(1) as $row) {
                DB::table('record_assignees')->where(['object_id' => $objectId, 'user_id' => $row->user_id])->update(['role' => 'co']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('record_assignees', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        foreach (DB::table('tasks')->whereNotNull('deadline')->get(['id', 'deadline']) as $task) {
            DB::table('tasks')->where('id', $task->id)->update(['deadline' => substr((string) $task->deadline, 0, 10).' 00:00:00']);
        }
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('start_date');
        });
    }
};
