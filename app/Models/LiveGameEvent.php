<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveGameEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_game_id', 'sequence', 'type', 'team_scope', 'player_id', 'period',
        'clock_seconds_remaining', 'occurred_at', 'payload', 'voids_event_id', 'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'period' => 'integer',
            'clock_seconds_remaining' => 'integer',
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<LiveGame, LiveGameEvent> */
    public function liveGame(): BelongsTo
    {
        return $this->belongsTo(LiveGame::class);
    }

    /** @return BelongsTo<Player, LiveGameEvent> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<User, LiveGameEvent> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return BelongsTo<LiveGameEvent, LiveGameEvent> */
    public function voidedEvent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'voids_event_id');
    }

    /** @return HasMany<LiveGameEvent, LiveGameEvent> */
    public function voidingEvents(): HasMany
    {
        return $this->hasMany(self::class, 'voids_event_id');
    }
}
