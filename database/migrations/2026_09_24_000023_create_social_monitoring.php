<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hashtags each team follows on social networks, stored without "#" and in lower case.
        Schema::create('social_hashtags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('tag');
            $table->timestamps();
            $table->unique(['workspace_id', 'tag']);
        });

        // One row per network: when it was last collected and whether it is failing.
        Schema::create('social_network_states', function (Blueprint $table) {
            $table->string('network')->primary();
            $table->dateTime('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            // Network-specific data kept between runs (e.g. Instagram hashtag ids).
            $table->json('state')->nullable();
            $table->timestamps();
        });

        // Social posts become mentions too, with their network and author.
        Schema::table('mentions', function (Blueprint $table) {
            $table->string('network')->nullable();
            $table->string('author')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mentions', function (Blueprint $table) {
            $table->dropColumn(['network', 'author']);
        });
        Schema::dropIfExists('social_network_states');
        Schema::dropIfExists('social_hashtags');
    }
};
