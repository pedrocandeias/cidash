<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An archived team keeps its data but stops working: no access for members, no ingestion, alerts or briefings.
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dateTime('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
