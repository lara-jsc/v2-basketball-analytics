<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerShotZoneProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'paint_made',
        'paint_attempted',
        'mid_range_made',
        'mid_range_attempted',
        'corner_3_left_made',
        'corner_3_left_attempted',
        'corner_3_right_made',
        'corner_3_right_attempted',
        'above_break_3_made',
        'above_break_3_attempted',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    /** @return BelongsTo<Player, PlayerShotZoneProfile> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
