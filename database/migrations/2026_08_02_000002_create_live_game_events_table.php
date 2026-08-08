<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_game_id')->constrained('live_games')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('type', 50)->index();
            $table->string('team_scope', 20);
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->unsignedTinyInteger('period');
            $table->unsignedSmallInteger('clock_seconds_remaining');
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->foreignId('voids_event_id')->nullable()->constrained('live_game_events')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['live_game_id', 'sequence']);
            $table->index('live_game_id');
            $table->index('player_id');
            $table->index('voids_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_game_events');
    }
};
