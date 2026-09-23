<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where to look when the alert is not about one record (a spike of mentions, a source).
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('url')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
