<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('voice_announcements', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->unique()
                ->constrained('pesanans')
                ->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->timestamp('expires_at', precision: 6)->nullable()->index();
            $table->string('audio_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('voice_announcements')->whereNotNull('order_id')->delete();

        Schema::table('voice_announcements', function (Blueprint $table) {
            $table->dropUnique('voice_announcements_order_id_unique');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['message', 'expires_at']);
            $table->string('audio_path')->nullable(false)->change();
        });
    }
};
