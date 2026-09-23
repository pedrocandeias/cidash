<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * News monitoring (ARCHITECTURE.md §2.3): a global source catalogue, news items
 * collected once for the whole instance, and a per-workspace triage state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind'); // rss, google_news, scraper
            $table->string('url', 2048);
            $table->json('config')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('poll_minutes')->default(15);
            $table->dateTime('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });

        Schema::create('workspace_sources', function (Blueprint $table) {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_priority')->default(false);
            $table->timestamps();

            $table->primary(['workspace_id', 'source_id']);
        });

        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->unsignedInteger('item_count')->default(1);
            $table->timestamps();
        });

        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('canonical_url', 2048);
            $table->string('url_hash', 64)->unique();
            $table->string('headline');
            // Only metadata is stored, never the full text (copyright).
            $table->text('summary')->nullable();
            $table->string('outlet')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('retrieved_at');
            $table->string('language', 10)->nullable();
            $table->foreignId('story_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('published_at');
        });

        // A news item as seen by one workspace: triage status and relevance.
        Schema::create('news_item_states', function (Blueprint $table) {
            $table->foreignUuid('id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_item_id')->constrained()->cascadeOnDelete();
            $table->string('headline');
            $table->string('status');
            $table->string('relevance')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'news_item_id']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_item_states');
        Schema::dropIfExists('news_items');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('workspace_sources');
        Schema::dropIfExists('sources');
    }
};
