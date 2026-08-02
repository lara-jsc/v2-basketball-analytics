<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveGameAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_game_id', 'player_id', 'type', 'severity', 'period', 'clock_seconds_remaining',
        'message', 'context', 'triggered_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'integer',
            'clock_seconds_remaining' => 'integer',
            'context' => 'array',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LiveGame, LiveGameAlert> */
    public function liveGame(): BelongsTo
    {
        return $this->belongsTo(LiveGame::class);
    }

    /** @return BelongsTo<Player, LiveGameAlert> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
