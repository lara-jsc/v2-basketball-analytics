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
    ];

    protected $casts = [
        'game_date' => 'date',
        'minutes_played' => 'float',
        'points' => 'integer',
        'field_goals_made' => 'integer',
        'field_goals_attempted' => 'integer',
        'three_pointers_made' => 'integer',
        'three_pointers_attempted' => 'integer',
        'free_throws_made' => 'integer',
        'free_throws_attempted' => 'integer',
        'offensive_rebounds' => 'integer',
        'defensive_rebounds' => 'integer',
        'rebounds' => 'integer',
        'assists' => 'integer',
        'steals' => 'integer',
        'blocks' => 'integer',
        'turnovers' => 'integer',
        'personal_fouls' => 'integer',
        'flagrant_fouls' => 'integer',
        'technical_fouls' => 'integer',
        'ejections' => 'integer',
        'disqualifications' => 'integer',
        'is_started' => 'boolean',
    ];

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
