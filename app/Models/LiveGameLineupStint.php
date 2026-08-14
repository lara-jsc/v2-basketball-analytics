<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveGameLineupStint extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_game_id', 'player_id', 'start_period', 'start_clock_seconds_remaining', 'end_period',
        'end_clock_seconds_remaining', 'started_at', 'ended_at', 'start_score_for', 'start_score_against',
        'end_score_for', 'end_score_against', 'duration_seconds', 'plus_minus',
    ];

    protected function casts(): array
    {
        return [
            'start_period' => 'integer',
            'start_clock_seconds_remaining' => 'integer',
            'end_period' => 'integer',
            'end_clock_seconds_remaining' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'start_score_for' => 'integer',
            'start_score_against' => 'integer',
            'end_score_for' => 'integer',
            'end_score_against' => 'integer',
            'duration_seconds' => 'integer',
            'plus_minus' => 'integer',
        ];
    }

    /** @return BelongsTo<LiveGame, LiveGameLineupStint> */
    public function liveGame(): BelongsTo
    {
        return $this->belongsTo(LiveGame::class);
    }

    /** @return BelongsTo<Player, LiveGameLineupStint> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
