<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_shot_zone_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('player_id')
                ->unique()
                ->constrained('players')
                ->cascadeOnDelete();

            // Paint (2PT inside the key)
            $table->unsignedSmallInteger('paint_made')->default(0);
            $table->unsignedSmallInteger('paint_attempted')->default(0);

            // Mid-range (2PT outside the key)
            $table->unsignedSmallInteger('mid_range_made')->default(0);
            $table->unsignedSmallInteger('mid_range_attempted')->default(0);

            // Left corner three
            $table->unsignedSmallInteger('corner_3_left_made')->default(0);
            $table->unsignedSmallInteger('corner_3_left_attempted')->default(0);

            // Right corner three
            $table->unsignedSmallInteger('corner_3_right_made')->default(0);
            $table->unsignedSmallInteger('corner_3_right_attempted')->default(0);

            // Above the break three
            $table->unsignedSmallInteger('above_break_3_made')->default(0);
            $table->unsignedSmallInteger('above_break_3_attempted')->default(0);

            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_shot_zone_profiles');
    }
};
