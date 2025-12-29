<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanvassingCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospect_id',
        'staff_id',
        'start_date',
        'current_stage',
        'status',
        'last_followup_date',
        'next_followup_date',
        'next_action',
    ];

    protected $casts = [
        'start_date' => 'date',
        'last_followup_date' => 'date',
        'next_followup_date' => 'date',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(CycleStatusLog::class)->orderBy('created_at', 'desc');
    }

    public function getNextStage(): int
    {
        $maxStage = $this->messages()->max('stage') ?? -1;
        return $maxStage + 1;
    }
}



