<?php

namespace App\Http\Controllers;

use App\Models\CanvassingCycle;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CanvassingController extends Controller
{
    /**
     * Delete all canvassing cycles with status 'success'
     */
    public function cleanupSuccess()
    {
        try {
            DB::beginTransaction();

            // Count records to be deleted for logging
            $count = CanvassingCycle::where('status', 'success')->count();

            // Delete records
            // Note: Cascade delete on messages table will handle the related messages
            CanvassingCycle::where('status', 'success')->delete();

            DB::commit();

            Log::info('Bulk delete success canvassing data', [
                'deleted_count' => $count,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Berhasil menghapus data canvassing yang sukses',
                'deleted_count' => $count
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cleanup success error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Gagal menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report data with flattened structure for table view
     */
    public function report(Request $request)
    {
        try {
            $user = auth()->user();

            // Base query
            $query = CanvassingCycle::with([
                'prospect',
                'staff',
                'messages' => function ($q) {
                    $q->orderBy('stage', 'asc');
                }
            ]);

            // Filter by Staff
            if ($request->has('staff_id') && $request->staff_id) {
                $query->where('staff_id', $request->staff_id);
            }

            // Filter by Date Range (based on start_date of the cycle)
            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('start_date', '>=', $request->start_date);
            }
            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('start_date', '<=', $request->end_date);
            }

            $cycles = $query->orderBy('start_date', 'desc')->get();

            // Transform data for frontend table
            $reportData = $cycles->map(function ($cycle) {
                $messagesByStage = [];
                foreach ($cycle->messages as $msg) {
                    $messagesByStage[$msg->stage] = [
                        'date' => $msg->submitted_at->format('Y-m-d'),
                        'screenshot_url' => url('storage/' . $msg->screenshot_path),
                        'status' => $msg->validation_status,
                        'id' => $msg->id
                    ];
                }

                // Determine category from first message (stage 0) or any message
                $category = $cycle->messages->first()->category ?? '-';

                return [
                    'id' => $cycle->id,
                    'staff_name' => $cycle->staff->name,
                    'merchant_name' => $cycle->prospect->instagram_username ?? 'Unknown',
                    'category' => $category,
                    'status' => $cycle->status, // ongoing / success / failed
                    'current_stage' => $cycle->current_stage,
                    'start_date' => $cycle->start_date->format('Y-m-d'),
                    'stages' => $messagesByStage // Keyed by stage number (0, 1, 2...)
                ];
            });

            return response()->json($reportData);

        } catch (\Exception $e) {
            Log::error('Report error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Gagal memuat laporan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
