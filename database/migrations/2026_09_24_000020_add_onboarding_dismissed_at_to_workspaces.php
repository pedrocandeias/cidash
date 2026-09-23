<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Managers can hide the "First steps" checklist before completing it.
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dateTime('onboarding_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('onboarding_dismissed_at');
        });
    }
};
