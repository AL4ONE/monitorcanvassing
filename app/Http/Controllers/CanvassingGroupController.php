<?php

namespace App\Http\Controllers;

use App\Models\CanvassingGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CanvassingGroupController extends Controller
{
    /**
     * List all canvassing groups
     */
    public function index(Request $request)
    {
        // Lazy update: Close expired groups
        CanvassingGroup::where('status', '!=', 'closed')
            ->where('end_date', '<', now()->toDateString())
            ->where('status', '!=', 'cancelled') // Don't close cancelled ones, though closed is final state usually. Let's assume cancelled is final too.
            ->update(['status' => 'closed']);

        $user = Auth::user();

        $query = CanvassingGroup::with(['creator', 'staff', 'prospects'])
            ->withCount('prospects');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filter by city
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }

        $groups = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add staff_stats to each group
        $groups->getCollection()->transform(function ($group) {
            $group->staff_stats = $group->staff->map(function ($staff) use ($group) {
                $startDate = \Carbon\Carbon::parse($staff->pivot->assigned_start_date);
                $endDate = \Carbon\Carbon::parse($staff->pivot->assigned_end_date);
                $days = $startDate->diffInDays($endDate) + 1;
                $target = $days * $group->target_per_day;

                $myProspects = $group->prospects->where('staff_id', $staff->id);
                $totalVisits = $myProspects->count();

                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'email' => $staff->email,
                    'assigned_start_date' => $staff->pivot->assigned_start_date,
                    'assigned_end_date' => $staff->pivot->assigned_end_date,
                    'total_days' => $days,
                    'total_visit' => $totalVisits,
                    'target' => $target,
                    'progress_percentage' => $target > 0 ? round(($totalVisits / $target) * 100, 1) : 0,
                ];
            });
            return $group;
        });

        return response()->json($groups);
    }


    /**
     * Create a new canvassing group
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat membuat canvassing group',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'target_per_day' => 'required|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'city' => 'required|string|max:255',
            'district' => 'required|string|max:255', // Now required per new flow logic usually, or nullable? Let's keep existing logic but add village.
            'village' => 'nullable|string|max:255',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'integer|exists:users,id',
        ]);

        $validated['created_by'] = $user->id;
        $validated['status'] = 'open';

        try {
            DB::beginTransaction();

            $group = CanvassingGroup::create($validated);

            // Assign staff if provided
            if (!empty($validated['staff_ids'])) {
                $attachData = [];
                foreach ($validated['staff_ids'] as $staffId) {
                    $attachData[$staffId] = [
                        'assigned_start_date' => $validated['start_date'],
                        'assigned_end_date' => $validated['end_date'],
                    ];
                }
                $group->staff()->attach($attachData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Canvassing group berhasil dibuat',
                'data' => $group->load('creator', 'staff'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create canvassing group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat canvassing group',
            ], 500);
        }
    }

    /**
     * Get detail of a canvassing group
     */
    public function show($id)
    {
        $group = CanvassingGroup::with(['creator', 'staff', 'prospects.staff'])
            ->findOrFail($id);

        $staffStats = $group->staff->map(function ($staff) use ($group) {
            $startDate = \Carbon\Carbon::parse($staff->pivot->assigned_start_date);
            $endDate = \Carbon\Carbon::parse($staff->pivot->assigned_end_date);
            $days = $startDate->diffInDays($endDate) + 1;
            $target = $days * $group->target_per_day;

            $myProspects = $group->prospects->where('staff_id', $staff->id);
            $registered = $myProspects->where('status', 'registered')->count();

            return [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'assigned_start_date' => $staff->pivot->assigned_start_date,
                'assigned_end_date' => $staff->pivot->assigned_end_date,
                'total_days' => $days,
                'total_visit' => $myProspects->count(),
                'registered' => $registered,
                'on_progress' => $myProspects->where('status', 'on_progress')->count(),
                'target' => $target,
                'progress_percentage' => $target > 0 ? round(($myProspects->count() / $target) * 100, 1) : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $group,
            'daily_stats' => $group->getDailyStats(),
            'staff_stats' => $staffStats,
        ]);
    }

    /**
     * Update a canvassing group
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat mengubah canvassing group',
            ], 403);
        }

        $group = CanvassingGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'target_per_day' => 'sometimes|integer|min:1',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'status' => ['sometimes', Rule::in(['open', 'on_progress', 'closed', 'cancelled'])],
            'cancel_reason' => 'nullable|string|max:500',
            'city' => 'sometimes|string|max:255',
            'district' => 'nullable|string|max:255',
            'village' => 'nullable|string|max:255',
        ]);

        // Require cancel_reason if status is cancelled
        if (isset($validated['status']) && $validated['status'] === 'cancelled' && empty($validated['cancel_reason'])) {
            return response()->json([
                'success' => false,
                'message' => 'Alasan pembatalan harus diisi',
            ], 422);
        }

        try {
            $group->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Canvassing group berhasil diupdate',
                'data' => $group->fresh()->load('creator', 'staff'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update canvassing group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate canvassing group',
            ], 500);
        }
    }

    /**
     * Delete a canvassing group
     */
    public function destroy($id)
    {
        $user = Auth::user();

        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat menghapus canvassing group',
            ], 403);
        }

        $group = CanvassingGroup::findOrFail($id);

        // Check if there are prospects
        if ($group->prospects()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus group yang sudah memiliki prospect',
            ], 422);
        }

        try {
            $group->delete();

            return response()->json([
                'success' => true,
                'message' => 'Canvassing group berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete canvassing group: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus canvassing group',
            ], 500);
        }
    }

    /**
     * Assign staff to a canvassing group
     */
    public function assignStaff(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat menugaskan staff',
            ], 403);
        }

        $validated = $request->validate([
            'staff_id' => 'required|integer|exists:users,id',
            'assigned_start_date' => 'required|date',
            'assigned_end_date' => 'required|date|after_or_equal:assigned_start_date',
        ]);

        $group = CanvassingGroup::findOrFail($id);

        // Check staff role
        $staff = User::findOrFail($validated['staff_id']);
        if ($staff->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya user dengan role staff yang dapat ditugaskan',
            ], 422);
        }

        // Check date overlap
        $canAssign = CanvassingGroup::canAssignStaff(
            $validated['staff_id'],
            $validated['assigned_start_date'],
            $validated['assigned_end_date'],
            $group->city,
            $group->district,
            $group->village,
            $id // Exclude current group for update scenarios
        );

        if (!$canAssign['can_assign']) {
            return response()->json([
                'success' => false,
                'message' => 'Staff sudah ditugaskan di group lain pada tanggal tersebut',
                'conflict' => $canAssign['conflict'],
            ], 422);
        }

        // Check if assignment dates are within group dates
        $groupStart = $group->start_date->format('Y-m-d');
        $groupEnd = $group->end_date->format('Y-m-d');

        if ($validated['assigned_start_date'] < $groupStart || $validated['assigned_end_date'] > $groupEnd) {
            return response()->json([
                'success' => false,
                'message' => "Tanggal assignment harus dalam range group ({$groupStart} - {$groupEnd})",
            ], 422);
        }

        try {
            // Sync or attach staff
            $group->staff()->syncWithoutDetaching([
                $validated['staff_id'] => [
                    'assigned_start_date' => $validated['assigned_start_date'],
                    'assigned_end_date' => $validated['assigned_end_date'],
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Staff berhasil ditugaskan',
                'data' => $group->fresh()->load('staff'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to assign staff: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menugaskan staff',
            ], 500);
        }
    }

    /**
     * Remove staff from a canvassing group
     */
    public function removeStaff($groupId, $staffId)
    {
        $user = Auth::user();

        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat menghapus penugasan staff',
            ], 403);
        }

        $group = CanvassingGroup::findOrFail($groupId);

        // Check if staff has prospects in this group
        $hasProspects = $group->prospects()
            ->where('staff_id', $staffId)
            ->exists();

        if ($hasProspects) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus staff yang sudah memiliki prospect di group ini',
            ], 422);
        }

        try {
            $group->staff()->detach($staffId);

            return response()->json([
                'success' => true,
                'message' => 'Staff berhasil dihapus dari group',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove staff: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus staff dari group',
            ], 500);
        }
    }

    /**
     * Get report for a canvassing group
     */
    public function report($id)
    {
        $group = CanvassingGroup::with(['staff', 'prospects.staff'])
            ->findOrFail($id);

        $dailyStats = $group->getDailyStats();

        // Get per-staff stats
        $staffStats = [];
        foreach ($group->staff as $staff) {
            $staffProspects = $group->prospects->where('staff_id', $staff->id);
            $staffStats[] = [
                'staff' => $staff,
                'total_visits' => $staffProspects->count(),
                'registered' => $staffProspects->where('status', 'registered')->count(),
                'on_progress' => $staffProspects->where('status', 'on_progress')->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'group' => $group,
                'daily_stats' => $dailyStats,
                'staff_stats' => $staffStats,
                'achievement' => $group->achievement_stats,
            ],
        ]);
    }

    /**
     * Get canvassing groups assigned to current staff
     */
    public function myGroups(Request $request)
    {
        // Lazy update: Close expired groups
        CanvassingGroup::where('status', '!=', 'closed')
            ->where('end_date', '<', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->update(['status' => 'closed']);

        $user = Auth::user();

        // Debug logging
        \Log::info('myGroups called', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'assigned_groups_count' => $user->assignedCanvassingGroups()->count(),
        ]);

        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint ini hanya untuk staff',
            ], 403);
        }

        $today = now()->format('Y-m-d');

        // Simpler query - just get the groups directly
        $groups = $user->assignedCanvassingGroups()
            ->with('creator')
            ->withCount('prospects')
            ->get();

        \Log::info('Groups fetched', ['count' => $groups->count()]);

        // Filter by active if requested
        $activeOnly = filter_var($request->get('active_only', false), FILTER_VALIDATE_BOOLEAN);

        if ($activeOnly) {
            $groups = $groups->filter(function ($group) use ($today) {
                return $group->pivot->assigned_start_date <= $today
                    && $group->pivot->assigned_end_date >= $today;
            })->values();
        }

        // Add staff's own stats to each group
        $groups->transform(function ($group) use ($user) {
            $myProspects = $group->prospects()->where('staff_id', $user->id)->get();
            $group->my_stats = [
                'total_visits' => $myProspects->count(),
                'registered' => $myProspects->where('status', 'registered')->count(),
                'on_progress' => $myProspects->where('status', 'on_progress')->count(),
            ];
            return $group;
        });

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    /**
     * Get available staff for assignment
     */
    public function availableStaff(Request $request, $id)
    {
        $user = Auth::user();

        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat melihat daftar staff',
            ], 403);
        }

        $group = CanvassingGroup::findOrFail($id);

        $startDate = $request->get('start_date', $group->start_date->format('Y-m-d'));
        $endDate = $request->get('end_date', $group->end_date->format('Y-m-d'));

        // Get all staff
        $allStaff = User::where('role', 'staff')->get();

        // Check availability for each staff
        $staffWithAvailability = $allStaff->map(function ($staff) use ($startDate, $endDate, $id, $group) {
            $availability = CanvassingGroup::canAssignStaff(
                $staff->id,
                $startDate,
                $endDate,
                $group->city,
                $group->district,
                $group->village,
                $id // Exclude current group
            );
            return [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'available' => $availability['can_assign'],
                'conflict' => $availability['conflict'] ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $staffWithAvailability,
        ]);
    }
    /**
     * Check available staff for new group creation
     */
    public function checkAvailableStaff(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat melihat daftar staff',
            ], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'city' => 'required|string',
            'district' => 'nullable|string',
            'village' => 'nullable|string',
            'group_id' => 'nullable|integer', // Optional, for excluding current group in edit mode if needed
        ]);

        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];
        $city = $validated['city'];
        $district = $validated['district'] ?? null;
        $village = $validated['village'] ?? null;
        $excludeGroupId = $validated['group_id'] ?? null;

        // Get all staff
        $allStaff = User::where('role', 'staff')->get();

        // Check availability for each staff
        $staffWithAvailability = $allStaff->map(function ($staff) use ($startDate, $endDate, $city, $district, $village, $excludeGroupId) {
            $availability = CanvassingGroup::canAssignStaff(
                $staff->id,
                $startDate,
                $endDate,
                $city,
                $district,
                $village,
                $excludeGroupId
            );
            return [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'available' => $availability['can_assign'],
                'conflict' => $availability['conflict'] ?? null,
            ];
        });

        // Determine assignment dates (same as group dates)
        $assignmentDates = [
            'assigned_start_date' => $startDate,
            'assigned_end_date' => $endDate,
        ];

        return response()->json([
            'success' => true,
            'data' => $staffWithAvailability,
            'assignment_dates' => $assignmentDates,
        ]);
    }
}
