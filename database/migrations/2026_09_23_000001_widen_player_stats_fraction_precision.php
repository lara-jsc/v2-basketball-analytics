<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 0–1 fractions are rounded to 4 decimals in PlayerStatsAggregator; decimal(5,2) cut them to 2. */
    private const COLUMNS = ['fg_pct', 'ft_pct', 'three_p_pct', 'sh_eff', 'efg_pct', 'ts_pct'];

    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->decimal($column, 6, 4)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->decimal($column, 5, 2)->nullable()->change();
            }
        });
    }
};
