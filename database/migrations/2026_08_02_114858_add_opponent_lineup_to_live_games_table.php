<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_games', function (Blueprint $table) {
            $table->json('opponent_starting_player_ids')->nullable()->after('active_player_ids');
            $table->json('opponent_active_player_ids')->nullable()->after('opponent_starting_player_ids');
        });
    }

    public function down(): void
    {
        Schema::table('live_games', function (Blueprint $table) {
            $table->dropColumn(['opponent_starting_player_ids', 'opponent_active_player_ids']);
        });
    }
};
