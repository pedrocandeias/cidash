<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assets: the team's images, videos and graphics (logos…), kept on the private disk.
        Schema::create('assets', function (Blueprint $table) {
            $table->foreignUuid('id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->string('title');
            $table->text('caption')->nullable();
            // Photographer, agency or author, as it must be credited.
            $table->string('credit')->nullable();
            // A key of the team's asset categories (workspace_options).
            $table->string('category')->nullable();
            // image, video or graphic: derived from the file.
            $table->string('kind');
            $table->date('taken_on')->nullable();
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index(['kind', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
