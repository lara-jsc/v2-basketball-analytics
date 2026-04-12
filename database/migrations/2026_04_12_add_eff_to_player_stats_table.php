<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EFF (Efficiency Rating) is a per-game average derived from player_histories.
     * Formula: avg(Pts + Reb + Ast + Stl + Blk − MissedFG − MissedFT − TO) per game.
     * Nullable until PlayerStatsComputationService writes it.
     */
    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->decimal('eff', 6, 2)->nullable()->after('plus_minus');
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $table->dropColumn('eff');
        });
    }
};
