<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('player_histories', function (Blueprint $table) {
            $table->float('plus_minus')->nullable()->after('is_started')
                  ->comment('Points scored by player team minus opponent team while player was on court');
        });
    }

    public function down(): void
    {
        Schema::table('player_histories', function (Blueprint $table) {
            $table->dropColumn('plus_minus');
        });
    }
};
