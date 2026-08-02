<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_game_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_game_id')->constrained('live_games')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('type', 50)->index();
            $table->string('severity', 20)->default('info');
            $table->unsignedTinyInteger('period')->nullable();
            $table->unsignedSmallInteger('clock_seconds_remaining')->nullable();
            $table->string('message', 255);
            $table->json('context')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();

            $table->index('live_game_id');
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_game_alerts');
    }
};
