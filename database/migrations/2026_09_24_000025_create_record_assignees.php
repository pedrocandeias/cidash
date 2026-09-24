<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** table => the single-person column it had */
    private const COLUMNS = [
        'tasks' => 'assigned_to',
        'content_items' => 'owner_id',
        'events' => 'responsible_user_id',
        'press_requests' => 'responsible_user_id',
    ];

    public function up(): void
    {
        // Who is responsible for a record: several people, for any record type.
        Schema::create('record_assignees', function (Blueprint $table) {
            $table->foreignUuid('object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['object_id', 'user_id']);
            $table->index('user_id');
        });

        foreach (self::COLUMNS as $table => $column) {
            DB::table('record_assignees')->insertUsing(
                ['object_id', 'user_id', 'created_at', 'updated_at'],
                DB::table($table)->whereNotNull($column)->select('id', $column, 'created_at', 'updated_at'),
            );

            Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                if ($table === 'tasks') {
                    $blueprint->dropIndex(['assigned_to', 'status']);
                }
                $blueprint->dropConstrainedForeignId($column);
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreignId($column)->nullable()->constrained('users')->nullOnDelete();
            });
            // Back to one person: the first one assigned.
            foreach (DB::table('record_assignees')->whereIn('object_id', DB::table($table)->select('id'))->orderBy('created_at')->get() as $row) {
                DB::table($table)->where('id', $row->object_id)->whereNull($column)->update([$column => $row->user_id]);
            }
        }

        Schema::dropIfExists('record_assignees');
    }
};
