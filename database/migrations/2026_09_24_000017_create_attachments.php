<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files on any record; stored on the private disk, served only to the team.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index('object_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
