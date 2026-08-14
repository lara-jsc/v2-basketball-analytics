<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_game_player_delegations')) {
            $this->ensureUniqueIndex('lgpd_triplet_u', ['live_game_id', 'coach_user_id', 'player_id']);
            $this->ensureUniqueIndex('lgpd_player_u', ['live_game_id', 'player_id']);

            return;
        }

        Schema::create('live_game_player_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_game_id')->constrained('live_games')->cascadeOnDelete();
            $table->foreignId('coach_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['live_game_id', 'coach_user_id', 'player_id'], 'lgpd_triplet_u');
            // Exclusive control: a player can be delegated to at most one coach for a given live game.
            $table->unique(['live_game_id', 'player_id'], 'lgpd_player_u');
        });
    }

    /** @param  array<int, string>  $columns */
    private function ensureUniqueIndex(string $indexName, array $columns): void
    {
        if ($this->indexExists($indexName)) {
            return;
        }

        Schema::table('live_game_player_delegations', function (Blueprint $table) use ($columns, $indexName): void {
            $table->unique($columns, $indexName);
        });
    }

    private function indexExists(string $indexName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS cnt
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND index_name = ?',
            ['live_game_player_delegations', $indexName],
        );

        return isset($row?->cnt) && (int) $row->cnt > 0;
    }

    public function down(): void
    {
        Schema::dropIfExists('live_game_player_delegations');
    }
};
