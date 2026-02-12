<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CanvassingGroupProspect extends Model
{
    use HasFactory;

    protected $fillable = [
        'canvassing_group_id',
        'staff_id',
        'business_name',
        'address',
        'photo',
        'contact_name',
        'contact_number',
        'status',
        'notes',
        'registered_at',
        'rejected_at',
        'rejection_reason',
        'visit_date',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'registered_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    /**
     * Get the canvassing group this prospect belongs to
     */
    public function canvassingGroup(): BelongsTo
    {
        return $this->belongsTo(CanvassingGroup::class);
    }

    /**
     * Get the staff who created this prospect
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Get photo URL (S3 or local)
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

        return url('storage/' . $this->photo);
    }
}
