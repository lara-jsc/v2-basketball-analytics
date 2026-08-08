<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveGamePlayerDelegation extends Model
{
    protected $table = 'live_game_player_delegations';

    protected $fillable = [
        'live_game_id',
        'coach_user_id',
        'player_id',
    ];

    public function liveGame(): BelongsTo
    {
        return $this->belongsTo(LiveGame::class, 'live_game_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }
}
