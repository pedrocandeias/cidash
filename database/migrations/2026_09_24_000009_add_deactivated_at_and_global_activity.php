<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Deactivated accounts cannot sign in (Admin → Users).
            $table->timestamp('deactivated_at')->nullable()->after('activated_at');
        });

        // Instance-wide actions (e.g. by the super admin) belong to no workspace.
        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->change();
            $table->foreignId('subject_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
