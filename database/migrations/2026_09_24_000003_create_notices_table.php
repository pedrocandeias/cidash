<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->foreignUuid('id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            // A future date schedules the notice; the author is objects.created_by.
            $table->dateTime('published_at');
            $table->dateTime('expires_at')->nullable();
            $table->string('priority');
            $table->boolean('pinned')->default(false);
            $table->timestamps();

            $table->index(['published_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
