<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_game_player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_game_id')->constrained('live_games')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->boolean('is_starter')->default(false);
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('minutes_seconds')->default(0);
            $table->integer('plus_minus')->default(0);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('field_goals_made')->default(0);
            $table->unsignedSmallInteger('field_goals_attempted')->default(0);
            $table->unsignedSmallInteger('three_pointers_made')->default(0);
            $table->unsignedSmallInteger('three_pointers_attempted')->default(0);
            $table->unsignedSmallInteger('free_throws_made')->default(0);
            $table->unsignedSmallInteger('free_throws_attempted')->default(0);
            $table->unsignedSmallInteger('offensive_rebounds')->default(0);
            $table->unsignedSmallInteger('defensive_rebounds')->default(0);
            $table->unsignedSmallInteger('rebounds')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedSmallInteger('steals')->default(0);
            $table->unsignedSmallInteger('blocks')->default(0);
            $table->unsignedSmallInteger('turnovers')->default(0);
            $table->unsignedSmallInteger('personal_fouls')->default(0);
            $table->unsignedSmallInteger('flagrant_fouls')->default(0);
            $table->unsignedSmallInteger('technical_fouls')->default(0);
            $table->timestamps();

            $table->unique(['live_game_id', 'player_id']);
            $table->index('live_game_id');
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_game_player_stats');
    }
};
