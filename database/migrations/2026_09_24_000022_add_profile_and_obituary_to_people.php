<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->text('career')->nullable();
            // CV kept on the private disk, with the name it was uploaded with.
            $table->string('cv_path')->nullable();
            $table->string('cv_name')->nullable();
            // Dead or Alive: an obituary prepared in advance, and the date of death once it happens.
            $table->text('obituary')->nullable();
            $table->dateTime('obituary_updated_at')->nullable();
            $table->date('deceased_on')->nullable();
        });

        // More photos of the person, for the media (the main one stays in people.photo_path).
        Schema::create('person_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_photos');
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['career', 'cv_path', 'cv_name', 'obituary', 'obituary_updated_at', 'deceased_on']);
        });
    }
};
