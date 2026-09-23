<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The core shared by every module (ARCHITECTURE.md §2.1–2.2): every domain record
 * has a row in `objects` sharing its id, and can be linked, tagged and commented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->index(['workspace_id', 'type']);
        });

        Schema::create('relations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('source_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignUuid('target_id')->constrained('objects')->cascadeOnDelete();
            $table->string('type');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_id', 'target_id', 'type']);
            $table->index('target_id');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->timestamps();

            $table->unique(['workspace_id', 'normalized_name']);
        });

        Schema::create('object_tag', function (Blueprint $table) {
            $table->foreignUuid('object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['object_id', 'tag_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // Kept (as null) when the object is deleted, so the audit trail survives.
            $table->foreignUuid('object_id')->nullable()->constrained('objects')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('object_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('relations');
        Schema::dropIfExists('objects');
    }
};
