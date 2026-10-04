<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'logo_path',
        'main_coach_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return HasMany<Player, Team> */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /** @return HasMany<CsvImport, Team> */
    public function csvImports(): HasMany
    {
        return $this->hasMany(CsvImport::class);
    }

    /** @return BelongsTo<User, Team> */
    public function mainCoach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'main_coach_user_id');
    }

    /** @return BelongsToMany<User, Team> */
    public function assistantCoaches(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_assistant_coaches')
            ->withTimestamps()
            ->orderBy('name');
    }

    /** @return HasMany<TeamJoinRequest, Team> */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(TeamJoinRequest::class);
    }

    /** @return HasMany<LiveGame, Team> */
    public function liveGamesAsHome(): HasMany
    {
        return $this->hasMany(LiveGame::class, 'home_team_id');
    }

    /** @return HasMany<LiveGame, Team> */
    public function liveGamesAsOpponent(): HasMany
    {
        return $this->hasMany(LiveGame::class, 'opponent_team_id');
    }
}
