<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStat extends Model
{
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
        'eff',                // nullable until PlayerStatsComputationService writes it
        'plus_minus',         // nullable until ComputePlayerPlusMinus Job completes
    ];

    protected $casts = [
        'three_p_pct'         => 'float',
        'fg_pct'              => 'float',
        'ft_pct'              => 'float',
        'sc_eff'              => 'float',
        'sh_eff'              => 'float',
        'pts'                 => 'float',
        'reb'                 => 'float',
        'ast'                 => 'float',
        'ast_to'              => 'float',
        'blk'                 => 'float',
        'stl'                 => 'float',
        'stl_to'              => 'float',
        'dr'                  => 'float',
        'offensive_rebounds'  => 'float',
        'min'                 => 'float',
        'pf'                  => 'float',
        'to_per_game'         => 'float',
        'eff'                 => 'float',
        'plus_minus'          => 'float',
    ];

    // -------------------------------------------------------------------------
    // Display Accessors — Formatted Output
    // -------------------------------------------------------------------------

    /** True Shooting % formatted: "65.1%" (stored as 0–1 decimal) */
    public function getTsPercentFormattedAttribute(): string
    {
        return $this->sc_eff !== null ? round($this->sc_eff * 100, 1) . '%' : '—';
    }

    /** Effective FG% formatted: "69.4%" (stored as 0–1 decimal) */
    public function getEfgPercentFormattedAttribute(): string
    {
        return $this->sh_eff !== null ? round($this->sh_eff * 100, 1) . '%' : '—';
    }

    /** Efficiency Rating formatted: "31.0" */
    public function getEfficiencyFormattedAttribute(): string
    {
        return $this->eff !== null ? number_format((float) $this->eff, 1) : '—';
    }

    /** Plus/Minus formatted for display: "+14", "-3", "0" */
    public function getFormattedPlusMinusAttribute(): string
    {
        if ($this->plus_minus === null) {
            return '—';
        }
        $val = (float) $this->plus_minus;
        return $val > 0 ? "+{$val}" : (string) $val;
    }

    /** @return BelongsTo<Player, PlayerStat> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
