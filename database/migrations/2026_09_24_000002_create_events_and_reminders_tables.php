<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->foreignUuid('id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type');
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('organizer')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('start_at');
        });

        // Personal reminders on any record, delivered as in-app notifications.
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('remind_at');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('events');
    }
};
