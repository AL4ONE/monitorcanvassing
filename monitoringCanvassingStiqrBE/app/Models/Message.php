<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'canvassing_cycle_id',
        'stage',
        'category',
        'screenshot_path',
        'screenshot_hash',
        'ocr_instagram_username',
        'ocr_message_snippet',
        'ocr_date',
        'submitted_at',
        'validation_status',
        'invalid_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'ocr_date' => 'date',
    ];

    public function canvassingCycle(): BelongsTo
    {
        return $this->belongsTo(CanvassingCycle::class);
    }

    public function qualityCheck(): HasOne
    {
        return $this->hasOne(QualityCheck::class);
    }
}

