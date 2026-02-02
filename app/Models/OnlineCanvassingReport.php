<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineCanvassingReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'business_name',
        'contact_name',
        'contact_number',
        'status',
        'notes',
        'rejection_reason',
        'photo',
        'visit_date',
    ];

    protected $casts = [
        'visit_date' => 'date',
    ];

    protected $appends = ['photo_url'];

    /**
     * Get the staff who created this report
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Get photo URL (S3 or local - public)
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        $disk = config('filesystems.default');

        if ($disk === 's3') {
            $baseUrl = config('filesystems.disks.s3.url');
            if (!empty($baseUrl)) {
                return rtrim($baseUrl, '/') . '/' . $this->photo;
            } else {
                $endpoint = config('filesystems.disks.s3.endpoint');
                $bucket = config('filesystems.disks.s3.bucket');
                return rtrim($endpoint, '/') . '/' . $bucket . '/' . $this->photo;
            }
        }

        // For local, ensure we use the public URL
        return url('storage/' . $this->photo);
    }
}
