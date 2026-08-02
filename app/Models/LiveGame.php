<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'home_team_id',
        'opponent_team_id',
        'created_by_user_id',
        'status',
        'game_date',
        'period_length_seconds',
        'current_period',
        'clock_seconds_remaining',
        'clock_running',
        'clock_started_at',
        'home_score',
        'opponent_score',
        'starting_player_ids',
        'active_player_ids',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'game_date' => 'date',
            'period_length_seconds' => 'integer',
            'current_period' => 'integer',
            'clock_seconds_remaining' => 'integer',
            'clock_running' => 'boolean',
            'clock_started_at' => 'datetime',
            'home_score' => 'integer',
            'opponent_score' => 'integer',
            'starting_player_ids' => 'array',
            'active_player_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Team, LiveGame> */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /** @return BelongsTo<Team, LiveGame> */
    public function opponentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'opponent_team_id');
    }

    /** @return BelongsTo<User, LiveGame> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<LiveGameEvent, LiveGame> */
    public function events(): HasMany
    {
        return $this->hasMany(LiveGameEvent::class);
    }

    /** @return HasMany<LiveGamePlayerStat, LiveGame> */
    public function playerStats(): HasMany
    {
        return $this->hasMany(LiveGamePlayerStat::class);
    }

    /** @return HasMany<LiveGameLineupStint, LiveGame> */
    public function lineupStints(): HasMany
    {
        return $this->hasMany(LiveGameLineupStint::class);
    }

    /** @return HasMany<LiveGameAlert, LiveGame> */
    public function alerts(): HasMany
    {
        return $this->hasMany(LiveGameAlert::class);
    }
}
