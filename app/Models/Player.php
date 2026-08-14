<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'first_name',
        'last_name',
        'jersey_number',
        'role',
        'height_feet',
        'weight_kg',
        'profile_picture_path',
        'is_active',
    ];

    protected $casts = [
        'jersey_number' => 'integer',
        'height_feet' => 'float',
        'weight_kg' => 'float',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Team, Player> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<PlayerStat, Player> */
    public function stats(): HasMany
    {
        return $this->hasMany(PlayerStat::class);
    }

    /** @return HasMany<PlayerHistory, Player> */
    public function histories(): HasMany
    {
        return $this->hasMany(PlayerHistory::class);
    }

    /** @return HasMany<LiveGameEvent, Player> */
    public function liveGameEvents(): HasMany
    {
        return $this->hasMany(LiveGameEvent::class);
    }

    /** @return HasMany<LiveGamePlayerStat, Player> */
    public function liveGameStats(): HasMany
    {
        return $this->hasMany(LiveGamePlayerStat::class);
    }

    /** @return HasMany<LiveGameLineupStint, Player> */
    public function liveGameLineupStints(): HasMany
    {
        return $this->hasMany(LiveGameLineupStint::class);
    }

    /** @return HasMany<LiveGameAlert, Player> */
    public function liveGameAlerts(): HasMany
    {
        return $this->hasMany(LiveGameAlert::class);
    }

    /** @return HasOne<PlayerShotZoneProfile, Player> */
    public function shotZoneProfile(): HasOne
    {
        return $this->hasOne(PlayerShotZoneProfile::class);
    }
}
