<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'pc',
        'sd',
        'three_p_pct',
        'three_pt',
        'fg_pct',
        'fg',
        'ft_pct',
        'ft',
        'sc_eff',
        'sh_eff',
        'pts',
        'reb',
        'ast',
        'ast_to',
        'blk',
        'stl',
        'stl_to',
        'dr',
        'offensive_rebounds', // maps to "OR" in CSV/display — "or" is a MySQL reserved word
        'min',
        'pf',
        'to_per_game',        // maps to "TO" in CSV/display — "to" is a MySQL reserved word
        'gp',
        'gs',
        'dd2',
        'td3',
        'dq',
        'eject',
        'flag',
        'tech',
        'plus_minus',         // nullable until ComputePlayerPlusMinus Job completes
        'eff',
        'efg_pct',
        'ts_pct',
    ];

    protected $casts = [
        'three_p_pct' => 'float',
        'fg_pct' => 'float',
        'ft_pct' => 'float',
        'sc_eff' => 'float',
        'sh_eff' => 'float',
        'pts' => 'float',
        'reb' => 'float',
        'ast' => 'float',
        'ast_to' => 'float',
        'blk' => 'float',
        'stl' => 'float',
        'stl_to' => 'float',
        'dr' => 'float',
        'offensive_rebounds' => 'float',
        'min' => 'float',
        'pf' => 'float',
        'to_per_game' => 'float',
        'plus_minus' => 'float',
        'eff' => 'float',
        'efg_pct' => 'float',
        'ts_pct' => 'float',
    ];

    /** @return BelongsTo<Player, PlayerStat> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
