<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerHistory extends Model
{
    use HasFactory;
    protected $fillable = [
        'player_id',
        'playing_team_id',
        'opponent_team_id',
        'game_date',
        'position_played',
        'minutes_played',
        'points',
        'field_goals_made',
        'field_goals_attempted',
        'three_pointers_made',
        'three_pointers_attempted',
        'free_throws_made',
        'free_throws_attempted',
        'offensive_rebounds',
        'defensive_rebounds',
        'rebounds',
        'assists',
        'steals',
        'blocks',
        'turnovers',
        'personal_fouls',
        'flagrant_fouls',
        'technical_fouls',
        'ejections',
        'disqualifications',
        'is_started',
        'notes',
        'plus_minus',
    ];

    protected $casts = [
        'game_date'               => 'date',
        'minutes_played'          => 'float',
        'points'                  => 'integer',
        'field_goals_made'        => 'integer',
        'field_goals_attempted'   => 'integer',
        'three_pointers_made'     => 'integer',
        'three_pointers_attempted'=> 'integer',
        'free_throws_made'        => 'integer',
        'free_throws_attempted'   => 'integer',
        'offensive_rebounds'      => 'integer',
        'defensive_rebounds'      => 'integer',
        'rebounds'                => 'integer',
        'assists'                 => 'integer',
        'steals'                  => 'integer',
        'blocks'                  => 'integer',
        'turnovers'               => 'integer',
        'personal_fouls'          => 'integer',
        'flagrant_fouls'          => 'integer',
        'technical_fouls'         => 'integer',
        'ejections'               => 'integer',
        'disqualifications'       => 'integer',
        'is_started'              => 'boolean',
        'plus_minus'              => 'float',
    ];

    // -------------------------------------------------------------------------
    // Computed Accessors — Per-Game Advanced Stats
    // -------------------------------------------------------------------------

    /**
     * Efficiency (EFF) — per game
     *
     * Formula: Pts + Reb + Ast + Stl + Blk − MissedFG − MissedFT − TO
     *   MissedFG = FGA − FGM
     *   MissedFT = FTA − FTM
     */
    public function getEfficiencyAttribute(): float
    {
        $missedFG = ($this->field_goals_attempted ?? 0) - ($this->field_goals_made ?? 0);
        $missedFT = ($this->free_throws_attempted ?? 0) - ($this->free_throws_made ?? 0);

        return (float) (
            ($this->points    ?? 0)
            + ($this->rebounds  ?? 0)
            + ($this->assists   ?? 0)
            + ($this->steals    ?? 0)
            + ($this->blocks    ?? 0)
            - $missedFG
            - $missedFT
            - ($this->turnovers ?? 0)
        );
    }

    /**
     * Effective Field Goal Percentage (eFG%) — per game
     *
     * Formula: (FGM + 0.5 × 3PM) / FGA
     * Returns 0 when FGA = 0 to avoid division by zero.
     */
    public function getEfgPercentAttribute(): float
    {
        $fga = $this->field_goals_attempted ?? 0;
        if ($fga === 0) {
            return 0.0;
        }

        return (float) round(
            (($this->field_goals_made ?? 0) + 0.5 * ($this->three_pointers_made ?? 0)) / $fga,
            4
        );
    }

    /**
     * True Shooting Percentage (TS%) — per game
     *
     * Formula: Pts / (2 × (FGA + 0.44 × FTA))
     * Returns 0 when denominator = 0 to avoid division by zero.
     */
    public function getTsPercentAttribute(): float
    {
        $denominator = 2 * (
            ($this->field_goals_attempted ?? 0)
            + 0.44 * ($this->free_throws_attempted ?? 0)
        );

        if ($denominator == 0) {
            return 0.0;
        }

        return (float) round(($this->points ?? 0) / $denominator, 4);
    }

    /**
     * Plus/Minus formatted for display — "+14", "-3", "0"
     */
    public function getFormattedPlusMinusAttribute(): string
    {
        $val = $this->plus_minus ?? 0;
        return $val > 0 ? "+{$val}" : (string) $val;
    }

    /** @return BelongsTo<Player, PlayerHistory> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Team, PlayerHistory> */
    public function playingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'playing_team_id');
    }

    /** @return BelongsTo<Team, PlayerHistory> */
    public function opponentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'opponent_team_id');
    }
}
