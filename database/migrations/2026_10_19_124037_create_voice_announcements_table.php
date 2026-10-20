<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('voice_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->string('audio_path');
            $table->unsignedSmallInteger('duration_seconds');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->boolean('is_playing')->default(false);
            $table->timestamps();
            $table->index(['is_playing', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_announcements');
    }
};
