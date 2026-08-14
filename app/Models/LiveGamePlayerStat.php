<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveGamePlayerStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_game_id', 'player_id', 'is_starter', 'is_active', 'minutes_seconds', 'plus_minus', 'points',
        'field_goals_made', 'field_goals_attempted', 'three_pointers_made', 'three_pointers_attempted',
        'free_throws_made', 'free_throws_attempted', 'offensive_rebounds', 'defensive_rebounds', 'rebounds',
        'assists', 'steals', 'blocks', 'turnovers', 'personal_fouls', 'flagrant_fouls', 'technical_fouls',
    ];

    protected function casts(): array
    {
        return [
            'is_starter' => 'boolean',
            'is_active' => 'boolean',
            'minutes_seconds' => 'integer',
            'plus_minus' => 'integer',
            'points' => 'integer',
            'field_goals_made' => 'integer',
            'field_goals_attempted' => 'integer',
            'three_pointers_made' => 'integer',
            'three_pointers_attempted' => 'integer',
            'free_throws_made' => 'integer',
            'free_throws_attempted' => 'integer',
            'offensive_rebounds' => 'integer',
            'defensive_rebounds' => 'integer',
            'rebounds' => 'integer',
            'assists' => 'integer',
            'steals' => 'integer',
            'blocks' => 'integer',
            'turnovers' => 'integer',
            'personal_fouls' => 'integer',
            'flagrant_fouls' => 'integer',
            'technical_fouls' => 'integer',
        ];
    }

    /** @return BelongsTo<LiveGame, LiveGamePlayerStat> */
    public function liveGame(): BelongsTo
    {
        return $this->belongsTo(LiveGame::class);
    }

    /** @return BelongsTo<Player, LiveGamePlayerStat> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
