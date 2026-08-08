<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_games', function (Blueprint $table) {
            $table->foreignId('home_main_coach_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('created_by_user_id');

            $table->foreignId('opponent_main_coach_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('home_main_coach_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('live_games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opponent_main_coach_user_id');
            $table->dropConstrainedForeignId('home_main_coach_user_id');
        });
    }
};
