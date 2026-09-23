<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Pessoas de interesse": manually curated expert profiles for the media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->foreignUuid('id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->string('name');
            $table->string('academic_title')->nullable();
            $table->string('affiliation')->nullable();
            $table->text('short_bio')->nullable();
            $table->text('bio')->nullable();
            $table->text('keywords')->nullable();
            $table->string('languages')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // Stored on the private disk, served to the team only.
            $table->string('photo_path')->nullable();
            $table->text('media_notes')->nullable();
            $table->date('consent_at')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('expertise_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->timestamps();

            $table->unique(['workspace_id', 'normalized_name']);
        });

        Schema::create('expertise_area_person', function (Blueprint $table) {
            $table->foreignId('expertise_area_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();

            $table->primary(['expertise_area_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expertise_area_person');
        Schema::dropIfExists('expertise_areas');
        Schema::dropIfExists('people');
    }
};
