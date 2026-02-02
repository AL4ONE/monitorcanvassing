<?php

namespace App\Http\Controllers;

use App\Models\CanvassingGroup;
use App\Models\CanvassingGroupProspect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CanvassingGroupProspectController extends Controller
{
    /**
     * List prospects in a canvassing group
     */
    public function index(Request $request, $groupId)
    {
        $user = Auth::user();
        $group = CanvassingGroup::findOrFail($groupId);

        // Check if user has access
        if ($user->role === 'staff') {
            // Staff can only see if they are assigned
            $isAssigned = $group->staff()->where('staff_id', $user->id)->exists();
            if (!$isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak ditugaskan di group ini',
                ], 403);
            }
        }

        $query = $group->prospects()->with('staff');

        // Filter by date
        if ($request->has('date')) {
            $query->whereDate('visit_date', $request->date);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by staff (for supervisor)
        if ($request->has('staff_id') && $user->role === 'supervisor') {
            $query->where('staff_id', $request->staff_id);
        }

        // Staff can only see their own prospects
        if ($user->role === 'staff') {
            $query->where('staff_id', $user->id);
        }

        $prospects = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $prospects,
        ]);
    }

    /**
     * Create a new prospect in canvassing group
     */
    public function store(Request $request, $groupId)
    {
        $user = Auth::user();

        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya staff yang dapat menambahkan prospect',
            ], 403);
        }

        $group = CanvassingGroup::findOrFail($groupId);

        // Check if staff is assigned to this group
        $assignment = $group->staff()
            ->where('staff_id', $user->id)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak ditugaskan di group ini',
            ], 403);
        }

        // Check if visit_date is within assignment range
        $today = now()->format('Y-m-d');
        $assignedStart = $assignment->pivot->assigned_start_date;
        $assignedEnd = $assignment->pivot->assigned_end_date;

        // Default visit_date to today
        $visitDate = $request->input('visit_date', $today);

        if ($visitDate < $assignedStart || $visitDate > $assignedEnd) {
            return response()->json([
                'success' => false,
                'message' => "Tanggal kunjungan harus dalam range penugasan Anda ({$assignedStart} - {$assignedEnd})",
            ], 422);
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'contact_name' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'status' => 'required|in:on_progress,registered',
            'notes' => 'nullable|string|max:1000',
            'visit_date' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $disk = config('filesystems.default') === 's3' ? 's3' : 'public';

                $photoPath = $file->storeAs('canvassing_photos', $fileName, [
                    'disk' => $disk,
                    'visibility' => 'public',
                ]);
            }

            $prospect = CanvassingGroupProspect::create([
                'canvassing_group_id' => $group->id,
                'staff_id' => $user->id,
                'business_name' => $validated['business_name'],
                'photo' => $photoPath,
                'contact_name' => $validated['contact_name'] ?? null,
                'contact_number' => $validated['contact_number'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'visit_date' => $visitDate,
            ]);

            // Update group status to on_progress if it's open
            if ($group->status === 'open') {
                $group->update(['status' => 'on_progress']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prospect berhasil ditambahkan',
                'data' => $prospect->load('staff'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create prospect: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan prospect',
            ], 500);
        }
    }

    /**
     * Update a prospect
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $prospect = CanvassingGroupProspect::findOrFail($id);

        // Staff can only update their own prospects
        if ($user->role === 'staff' && $prospect->staff_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat mengubah prospect orang lain',
            ], 403);
        }

        $validated = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'contact_name' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'status' => 'sometimes|in:on_progress,registered',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $disk = config('filesystems.default') === 's3' ? 's3' : 'public';

                // Delete old photo if exists
                if ($prospect->photo) {
                    Storage::disk($disk)->delete($prospect->photo);
                }

                $validated['photo'] = $file->storeAs('canvassing_photos', $fileName, [
                    'disk' => $disk,
                    'visibility' => 'public',
                ]);
            }

            $prospect->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prospect berhasil diupdate',
                'data' => $prospect->fresh()->load('staff'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update prospect: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate prospect',
            ], 500);
        }
    }

    /**
     * Update prospect status
     */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();
        $prospect = CanvassingGroupProspect::findOrFail($id);

        // Staff can only update their own prospects
        if ($user->role === 'staff' && $prospect->staff_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat mengubah prospect orang lain',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:on_progress,registered',
        ]);

        try {
            $prospect->update(['status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'message' => 'Status berhasil diupdate',
                'data' => $prospect->fresh(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update prospect status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate status',
            ], 500);
        }
    }

    /**
     * Delete a prospect
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $prospect = CanvassingGroupProspect::findOrFail($id);

        // Staff can only delete their own prospects
        if ($user->role === 'staff' && $prospect->staff_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus prospect orang lain',
            ], 403);
        }

        try {
            // Delete photo if exists
            if ($prospect->photo) {
                $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
                Storage::disk($disk)->delete($prospect->photo);
            }

            $prospect->delete();

            return response()->json([
                'success' => true,
                'message' => 'Prospect berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete prospect: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus prospect',
            ], 500);
        }
    }

    /**
     * Get today's statistics for staff in a group
     */
    public function todayStats($groupId)
    {
        $user = Auth::user();

        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint ini hanya untuk staff',
            ], 403);
        }

        $group = CanvassingGroup::findOrFail($groupId);

        // Check if staff is assigned
        $isAssigned = $group->staff()->where('staff_id', $user->id)->exists();
        if (!$isAssigned) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak ditugaskan di group ini',
            ], 403);
        }

        $today = now()->toDateString();

        $todayProspects = $group->prospects()
            ->where('staff_id', $user->id)
            ->whereDate('visit_date', $today)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $today,
                'target' => $group->target_per_day,
                'total_visits' => $todayProspects->count(),
                'registered' => $todayProspects->where('status', 'registered')->count(),
                'on_progress' => $todayProspects->where('status', 'on_progress')->count(),
                'remaining' => max(0, $group->target_per_day - $todayProspects->where('status', 'registered')->count()),
            ],
        ]);
    }
}
