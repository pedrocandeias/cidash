<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('include_terms');
            $table->json('exclude_terms')->nullable();
            $table->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('category')->nullable();
            // Also search Google News for the rule's terms.
            $table->boolean('google_news')->default(true);
            $table->boolean('active')->default(true);
            $table->dateTime('last_fetched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mentions', function (Blueprint $table) {
            $table->foreignUuid('id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('monitoring_rules')->nullOnDelete();
            $table->string('url', 2048);
            $table->string('url_hash', 64);
            $table->string('headline');
            $table->text('excerpt')->nullable();
            $table->string('outlet')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('matched_keyword');
            $table->string('category')->nullable();
            $table->string('relevance')->nullable();
            $table->string('review_status');
            $table->timestamps();

            $table->unique(['workspace_id', 'url_hash']);
            $table->index(['workspace_id', 'review_status']);
        });

        Schema::table('workspace_sources', function (Blueprint $table) {
            // General feeds only bring the articles that match the team's rules.
            $table->boolean('only_matching')->default(true)->after('is_priority');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_sources', function (Blueprint $table) {
            $table->dropColumn('only_matching');
        });
        Schema::dropIfExists('mentions');
        Schema::dropIfExists('monitoring_rules');
    }
};
