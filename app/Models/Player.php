<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
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
        'height_feet'   => 'float',
        'weight_kg'     => 'float',
        'is_active'     => 'boolean',
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
}
