<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per workspace and catalogue type, created on first evaluation.
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type');
            $table->json('params')->nullable();
            $table->string('severity');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'rule_type']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('alert_rules')->cascadeOnDelete();
            $table->foreignUuid('object_id')->nullable()->constrained('objects')->cascadeOnDelete();
            $table->string('severity');
            // English i18n key describing the problem; `title` names what it is about.
            $table->string('message');
            $table->string('title');
            $table->dateTime('due_at')->nullable();
            $table->string('dedupe_key');
            $table->string('status');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'dedupe_key']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
    }
};
