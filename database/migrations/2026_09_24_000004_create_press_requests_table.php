<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_requests', function (Blueprint $table) {
            $table->foreignUuid('id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->string('subject');
            $table->text('request')->nullable();
            // Professional contacts, as text (ARCHITECTURE.md §2.3, §7 "Dados de pessoas").
            $table->string('journalist')->nullable();
            $table->string('media_outlet')->nullable();
            $table->string('contact')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('deadline')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('response_notes')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_requests');
    }
};
