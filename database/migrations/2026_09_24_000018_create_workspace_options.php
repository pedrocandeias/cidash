<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-team lists (event types, content formats). The key is what records store.
        Schema::create('workspace_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('list');
            $table->string('key');
            $table->string('label');
            $table->string('normalized_label');
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'list', 'key']);
            $table->unique(['workspace_id', 'list', 'normalized_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_options');
    }
};
