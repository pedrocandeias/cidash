<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshots: what was known when the briefing was generated.
        Schema::create('briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->date('period_start');
            $table->date('period_end');
            $table->dateTime('generated_at');
            $table->json('content');
            $table->text('ai_summary')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'kind', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('briefings');
    }
};
