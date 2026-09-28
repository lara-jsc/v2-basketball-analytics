<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'team_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'team_id' => 'integer',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** @return BelongsTo<Team, User> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<LiveGame, User> */
    public function createdLiveGames(): HasMany
    {
        return $this->hasMany(LiveGame::class, 'created_by_user_id');
    }

    /** @return HasMany<Team, User> */
    public function mainCoachedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'main_coach_user_id');
    }

    /** @return BelongsToMany<Team, User> */
    public function assistantCoachedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_assistant_coaches')
            ->withTimestamps();
    }

    /** @return HasMany<LiveGameEvent, User> */
    public function recordedLiveGameEvents(): HasMany
    {
        return $this->hasMany(LiveGameEvent::class, 'recorded_by_user_id');
    }
}
