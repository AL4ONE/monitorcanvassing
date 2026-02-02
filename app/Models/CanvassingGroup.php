<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CanvassingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'target_per_day',
        'start_date',
        'end_date',
        'status',
        'cancel_reason',
        'city',
        'district',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'target_per_day' => 'integer',
    ];

    protected $appends = ['total_days', 'total_target', 'achievement_stats'];

    /**
     * Staff assigned to this canvassing group
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'canvassing_group_staff', 'canvassing_group_id', 'staff_id')
            ->withPivot('assigned_start_date', 'assigned_end_date')
            ->withTimestamps();
    }

    /**
     * Prospects/merchants in this canvassing group
     */
    public function prospects(): HasMany
    {
        return $this->hasMany(CanvassingGroupProspect::class);
    }

    /**
     * User who created this group
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get total days of campaign
     */
    public function getTotalDaysAttribute(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Get total target (days × target_per_day × staff_count)
     * Target is per staff per day
     */
    public function getTotalTargetAttribute(): int
    {
        $staffCount = $this->staff()->count();
        return $this->total_days * $this->target_per_day * max(1, $staffCount);
    }

    /**
     * Get achievement statistics
     */
    public function getAchievementStatsAttribute(): array
    {
        $prospects = $this->prospects ?? collect();

        $totalVisits = $prospects->count();
        $registered = $prospects->where('status', 'registered')->count();
        $onProgress = $prospects->where('status', 'on_progress')->count();

        return [
            'total_visits' => $totalVisits,
            'registered' => $registered,
            'on_progress' => $onProgress,
            'target' => $this->total_target,
            'percentage' => $this->total_target > 0
                ? round(($totalVisits / $this->total_target) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get daily statistics for this group
     * Target per day = target_per_day × number of staff active on that day
     */
    public function getDailyStats(): array
    {
        $stats = [];
        $currentDate = $this->start_date->copy();
        $allStaff = $this->staff;

        while ($currentDate <= $this->end_date) {
            $dateStr = $currentDate->toDateString();
            $dayProspects = $this->prospects()->whereDate('visit_date', $dateStr)->get();

            // Count staff active on this date
            $activeStaff = $allStaff->filter(function ($staff) use ($dateStr) {
                return $staff->pivot->assigned_start_date <= $dateStr
                    && $staff->pivot->assigned_end_date >= $dateStr;
            })->count();

            $stats[] = [
                'date' => $dateStr,
                'day' => $currentDate->format('l'),
                'total_visit' => $dayProspects->count(),
                'registered' => $dayProspects->where('status', 'registered')->count(),
                'on_progress' => $dayProspects->where('status', 'on_progress')->count(),
                'target' => $this->target_per_day * max(1, $activeStaff),
                'active_staff' => $activeStaff,
            ];

            $currentDate->addDay();
        }

        return $stats;
    }

    /**
     * Check if a staff can be assigned (no date overlap)
     */
    public static function canAssignStaff(int $staffId, string $startDate, string $endDate, ?int $excludeGroupId = null): array
    {
        $query = \DB::table('canvassing_group_staff')
            ->where('staff_id', $staffId)
            ->where(function ($q) use ($startDate, $endDate) {
                // Check for any overlap
                $q->where(function ($inner) use ($startDate, $endDate) {
                    $inner->where('assigned_start_date', '<=', $endDate)
                        ->where('assigned_end_date', '>=', $startDate);
                });
            });

        if ($excludeGroupId) {
            $query->where('canvassing_group_id', '!=', $excludeGroupId);
        }

        $conflicting = $query->join('canvassing_groups', 'canvassing_groups.id', '=', 'canvassing_group_staff.canvassing_group_id')
            ->select('canvassing_groups.name', 'canvassing_group_staff.assigned_start_date', 'canvassing_group_staff.assigned_end_date')
            ->first();

        if ($conflicting) {
            return [
                'can_assign' => false,
                'conflict' => [
                    'group_name' => $conflicting->name,
                    'start_date' => $conflicting->assigned_start_date,
                    'end_date' => $conflicting->assigned_end_date,
                ],
            ];
        }

        return ['can_assign' => true];
    }
}
