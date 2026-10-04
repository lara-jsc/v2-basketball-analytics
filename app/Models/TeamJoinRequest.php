<?php

namespace App\Models;

use App\Enums\JoinRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamJoinRequest extends Model
{
    protected $fillable = ['team_id', 'user_id', 'status', 'decided_by_user_id', 'decided_at'];

    protected $casts = [
        'status' => JoinRequestStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<Team, TeamJoinRequest> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, TeamJoinRequest> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, TeamJoinRequest> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
