<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImport extends Model
{
    use HasFactory;
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'team_id',
        'filename',
        'status',
        'rows_imported',
        'error_log',
    ];

    protected $casts = [
        'rows_imported' => 'integer',
    ];

    /** @return BelongsTo<Team, CsvImport> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
