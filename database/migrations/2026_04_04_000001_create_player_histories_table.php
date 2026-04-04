<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('player_id')
                ->constrained('players')
                ->cascadeOnDelete();

            $table->foreignId('playing_team_id')
                ->constrained('teams')
                ->restrictOnDelete();

            $table->foreignId('opponent_team_id')
                ->constrained('teams')
                ->restrictOnDelete();

            $table->date('game_date');

            $table->string('position_played', 50)->nullable();
            $table->decimal('minutes_played', 5, 2)->nullable();

            $table->unsignedTinyInteger('points')->nullable();
            $table->unsignedTinyInteger('field_goals_made')->nullable();
            $table->unsignedTinyInteger('field_goals_attempted')->nullable();
            $table->unsignedTinyInteger('three_pointers_made')->nullable();
            $table->unsignedTinyInteger('three_pointers_attempted')->nullable();
            $table->unsignedTinyInteger('free_throws_made')->nullable();
            $table->unsignedTinyInteger('free_throws_attempted')->nullable();
            $table->unsignedTinyInteger('offensive_rebounds')->nullable();  // avoids `OR` reserved word
            $table->unsignedTinyInteger('defensive_rebounds')->nullable();
            $table->unsignedTinyInteger('rebounds')->nullable();
            $table->unsignedTinyInteger('assists')->nullable();
            $table->unsignedTinyInteger('steals')->nullable();
            $table->unsignedTinyInteger('blocks')->nullable();
            $table->unsignedTinyInteger('turnovers')->nullable();           // avoids `TO` reserved word
            $table->unsignedTinyInteger('personal_fouls')->nullable();
            $table->unsignedTinyInteger('flagrant_fouls')->nullable();
            $table->unsignedTinyInteger('technical_fouls')->nullable();
            $table->unsignedTinyInteger('ejections')->nullable();
            $table->unsignedTinyInteger('disqualifications')->nullable();

            $table->boolean('is_started')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();

            // Upsert key: one log per player per opponent per date
            $table->unique(['player_id', 'game_date', 'opponent_team_id'], 'uq_player_game_opponent');

            // Note: playing_team_id != opponent_team_id is enforced at the application layer
            // (StorePlayerHistoryRequest, UpdatePlayerHistoryRequest, UpsertPlayerHistoryAction).
            // A DB-level CHECK constraint requires MySQL in production — add via a separate
            // migration when deploying to MySQL.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_histories');
    }
};
