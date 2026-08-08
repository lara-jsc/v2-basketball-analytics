<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class LiveGame extends Model
{
    use HasFactory;

    public const STATUS_SETUP = 'setup';

    public const STATUS_LIVE = 'live';

    public const STATUS_FINISHED = 'finished';

    public const SIDE_HOME = 'home';

    public const SIDE_OPPONENT = 'opponent';

    public const STATUSES = [
        self::STATUS_SETUP,
        self::STATUS_LIVE,
        self::STATUS_FINISHED,
    ];

    protected $fillable = [
        'home_team_id',
        'opponent_team_id',
        'created_by_user_id',
        'home_main_coach_user_id',
        'opponent_main_coach_user_id',
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
        'opponent_starting_player_ids',
        'opponent_active_player_ids',
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
            'opponent_starting_player_ids' => 'array',
            'opponent_active_player_ids' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function setStatusAttribute(mixed $value): void
    {
        if (! is_string($value) || ! in_array($value, self::STATUSES, true)) {
            $invalidStatus = is_scalar($value) ? (string) $value : get_debug_type($value);

            throw new InvalidArgumentException(
                "Invalid live game status [{$invalidStatus}]. Allowed statuses: ".implode(', ', self::STATUSES).'.'
            );
        }

        $this->attributes['status'] = $value;
    }

    public function isCreator(User $user): bool
    {
        return $this->created_by_user_id !== null && (int) $this->created_by_user_id === (int) $user->id;
    }

    public function isMainCoach(User $user): bool
    {
        foreach ([$this->home_main_coach_user_id, $this->opponent_main_coach_user_id] as $mainCoachUserId) {
            if ($mainCoachUserId !== null && (int) $mainCoachUserId === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    public function isParticipant(User $user): bool
    {
        if ($this->isCreator($user)) {
            return true;
        }

        if ($user->team_id === null) {
            return false;
        }

        return in_array((int) $user->team_id, [(int) $this->home_team_id, (int) $this->opponent_team_id], true);
    }

    /** @return self::SIDE_HOME|self::SIDE_OPPONENT|null */
    public function sideFor(User $user): ?string
    {
        if ($user->team_id === null) {
            return null;
        }

        if ((int) $user->team_id === (int) $this->home_team_id) {
            return self::SIDE_HOME;
        }

        if ((int) $user->team_id === (int) $this->opponent_team_id) {
            return self::SIDE_OPPONENT;
        }

        return null;
    }

    /** @return self::SIDE_HOME|self::SIDE_OPPONENT|null */
    public function sideForPlayer(int $playerId): ?string
    {
        $teamId = (int) (Player::query()->whereKey($playerId)->value('team_id') ?? 0);

        if ($teamId === (int) $this->home_team_id) {
            return self::SIDE_HOME;
        }

        if ($teamId === (int) $this->opponent_team_id) {
            return self::SIDE_OPPONENT;
        }

        return null;
    }

    /** @return list<int> */
    public function startingPlayerIdsForSide(string $side): array
    {
        $ids = $side === self::SIDE_OPPONENT
            ? $this->opponent_starting_player_ids
            : $this->starting_player_ids;

        return array_values(array_map('intval', $ids ?? []));
    }

    /** @return list<int> */
    public function activePlayerIdsForSide(string $side): array
    {
        if ($side === self::SIDE_OPPONENT) {
            return array_values(array_map(
                'intval',
                $this->opponent_active_player_ids ?? $this->opponent_starting_player_ids ?? [],
            ));
        }

        return array_values(array_map(
            'intval',
            $this->active_player_ids ?? $this->starting_player_ids ?? [],
        ));
    }

    public function homeLineupReady(): bool
    {
        return count($this->startingPlayerIdsForSide(self::SIDE_HOME)) === 5;
    }

    public function opponentLineupReady(): bool
    {
        return count($this->startingPlayerIdsForSide(self::SIDE_OPPONENT)) === 5;
    }

    public function bothLineupsReady(): bool
    {
        return $this->homeLineupReady() && $this->opponentLineupReady();
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
