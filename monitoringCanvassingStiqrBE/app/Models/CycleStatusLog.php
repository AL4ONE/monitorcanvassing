<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CycleStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'canvassing_cycle_id',
        'old_status',
        'new_status',
        'changed_by',
        'notes',
    ];

    public function canvassingCycle(): BelongsTo
    {
        return $this->belongsTo(CanvassingCycle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
