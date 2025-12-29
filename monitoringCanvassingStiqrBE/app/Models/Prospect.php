<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    use HasFactory;

    protected $fillable = [
        'instagram_username',
        'category',
        'business_type',
        'channel',
        'instagram_link',
        'contact_number',
    ];

    public function canvassingCycles(): HasMany
    {
        return $this->hasMany(CanvassingCycle::class);
    }
}



