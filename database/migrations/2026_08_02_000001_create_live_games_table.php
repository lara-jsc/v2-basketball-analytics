<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('opponent_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('setup')->index();
            $table->date('game_date')->nullable();
            $table->unsignedSmallInteger('period_length_seconds')->default(600);
            $table->unsignedTinyInteger('current_period')->default(1);
            $table->unsignedSmallInteger('clock_seconds_remaining')->default(600);
            $table->boolean('clock_running')->default(false);
            $table->timestamp('clock_started_at')->nullable();
            $table->unsignedSmallInteger('home_score')->default(0);
            $table->unsignedSmallInteger('opponent_score')->default(0);
            $table->json('starting_player_ids')->nullable();
            $table->json('active_player_ids')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('home_team_id');
            $table->index('opponent_team_id');
            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_games');
    }
};
