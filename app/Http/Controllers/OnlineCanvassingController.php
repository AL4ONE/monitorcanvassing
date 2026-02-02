<?php

namespace App\Http\Controllers;

use App\Models\OnlineCanvassingReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnlineCanvassingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = OnlineCanvassingReport::query(); // Start query builder

        // If not supervisor, only show own reports
        if ($user->role !== 'supervisor') {
            $query->where('staff_id', $user->id);
        } else {
            // Supervisor filters
            if ($request->has('staff_id')) {
                $query->where('staff_id', $request->staff_id);
            }
        }

        if ($request->has('date')) {
            $query->whereDate('visit_date', $request->date);
        }

        // Include staff relation for supervisor to see who reported
        $query->with('staff:id,name');

        $reports = $query->orderBy('visit_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:255',
            'status' => 'required|in:on_progress,registered,rejected',
            'notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'nullable|required_if:status,rejected|string|max:1000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
            'visit_date' => 'required|date',
        ]);

        try {
            $imagePath = null;
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                // Ensure usage of public disk for local dev, or S3 if configured
                $disk = config('filesystems.default') === 's3' ? 's3' : 'public';

                $imagePath = $file->storeAs('online_canvassing_proofs', $fileName, [
                    'disk' => $disk,
                    'visibility' => 'public',
                ]);
            }

            $report = OnlineCanvassingReport::create([
                'staff_id' => $user->id,
                'business_name' => $validated['business_name'],
                'contact_name' => $validated['contact_name'],
                'contact_number' => $validated['contact_number'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'rejection_reason' => $validated['status'] === 'rejected' ? ($validated['rejection_reason'] ?? null) : null,
                'photo' => $imagePath,
                'visit_date' => $validated['visit_date'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dibuat',
                'data' => $report,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create online report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat laporan',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $report = OnlineCanvassingReport::where('staff_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:255',
            'status' => 'sometimes|in:on_progress,registered,rejected',
            'notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'nullable|required_if:status,rejected|string|max:1000',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'visit_date' => 'sometimes|date',
        ]);

        try {
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $disk = config('filesystems.default') === 's3' ? 's3' : 'public';

                // Delete old image
                if ($report->photo) {
                    Storage::disk($disk)->delete($report->photo);
                }

                $validated['photo'] = $file->storeAs('online_canvassing_proofs', $fileName, [
                    'disk' => $disk,
                    'visibility' => 'public',
                ]);
            }

            if (isset($validated['status']) && $validated['status'] !== 'rejected') {
                $validated['rejection_reason'] = null; // Clear rejection reason if status changed
            }

            $report->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil diupdate',
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update online report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate laporan',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $report = OnlineCanvassingReport::where('staff_id', $user->id)->findOrFail($id);

        try {
            if ($report->photo) {
                $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
                Storage::disk($disk)->delete($report->photo);
            }

            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete online report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus laporan',
            ], 500);
        }
    }
}
