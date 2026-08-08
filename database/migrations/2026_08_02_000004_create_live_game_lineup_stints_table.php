<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_game_lineup_stints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_game_id')->constrained('live_games')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedTinyInteger('start_period');
            $table->unsignedSmallInteger('start_clock_seconds_remaining');
            $table->unsignedTinyInteger('end_period')->nullable();
            $table->unsignedSmallInteger('end_clock_seconds_remaining')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedSmallInteger('start_score_for')->default(0);
            $table->unsignedSmallInteger('start_score_against')->default(0);
            $table->unsignedSmallInteger('end_score_for')->nullable();
            $table->unsignedSmallInteger('end_score_against')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->integer('plus_minus')->default(0);
            $table->timestamps();

            $table->index(['live_game_id', 'ended_at']);
            $table->index('live_game_id');
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_game_lineup_stints');
    }
};
