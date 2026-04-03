<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reserved word notes:
     *  - `or`  → stored as `offensive_rebounds`  (maps to "OR"  in CSV/display)
     *  - `to`  → stored as `to_per_game`          (maps to "TO"  in CSV/display)
     *  - `plus_minus` is nullable until ComputePlayerPlusMinus Job completes
     */
    public function up(): void
    {
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();

            // Position / spatial
            $table->string('pc', 50)->nullable();   // Position on Court
            $table->string('sd', 50)->nullable();   // Spatial Data

            // Shooting
            $table->decimal('three_p_pct', 5, 2)->nullable();  // 3P%
            $table->string('three_pt', 20)->nullable();        // 3PT made-attempted
            $table->decimal('fg_pct', 5, 2)->nullable();       // FG%
            $table->string('fg', 20)->nullable();              // FG made-attempted
            $table->decimal('ft_pct', 5, 2)->nullable();       // FT%
            $table->string('ft', 20)->nullable();              // FT made-attempted
            $table->decimal('sc_eff', 5, 2)->nullable();       // Scoring Efficiency
            $table->decimal('sh_eff', 5, 2)->nullable();       // Shooting Efficiency

            // Counting stats
            $table->decimal('pts', 5, 2)->nullable();
            $table->decimal('reb', 5, 2)->nullable();
            $table->decimal('ast', 5, 2)->nullable();
            $table->decimal('ast_to', 5, 2)->nullable();       // AST/TO ratio
            $table->decimal('blk', 5, 2)->nullable();
            $table->decimal('stl', 5, 2)->nullable();
            $table->decimal('stl_to', 5, 2)->nullable();       // STL/TO ratio
            $table->decimal('dr', 5, 2)->nullable();           // Defensive Rebounds
            $table->decimal('offensive_rebounds', 5, 2)->nullable(); // "OR" in CSV — reserved word avoided
            $table->decimal('min', 5, 2)->nullable();
            $table->decimal('pf', 5, 2)->nullable();

            // Turnover — "to" is a MySQL reserved word; stored as to_per_game
            $table->decimal('to_per_game', 5, 2)->nullable();

            // Game counts
            $table->tinyInteger('gp')->nullable();  // Games Played
            $table->tinyInteger('gs')->nullable();  // Games Started
            $table->tinyInteger('dd2')->nullable(); // Double-Double
            $table->tinyInteger('td3')->nullable(); // Triple-Double

            // Disciplinary
            $table->tinyInteger('dq')->nullable();   // Disqualifications
            $table->tinyInteger('eject')->nullable(); // Ejections
            $table->tinyInteger('flag')->nullable();  // Flagrant Fouls
            $table->tinyInteger('tech')->nullable();  // Technical Fouls

            // Computed by Python — nullable until ComputePlayerPlusMinus Job completes
            $table->decimal('plus_minus', 5, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
