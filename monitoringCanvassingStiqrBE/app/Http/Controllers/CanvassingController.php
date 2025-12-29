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
    /**
     * Delete all messages with status 'valid' and their images
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $cycle = CanvassingCycle::findOrFail($id);

            $request->validate([
                'status' => 'nullable|string',
                'next_followup_date' => 'nullable|date',
                'next_action' => 'nullable|string',
            ]);

            $updates = [];
            $logData = null;

            if ($request->has('status') && $request->status !== $cycle->status) {
                // Prepare log
                $logData = [
                    'canvassing_cycle_id' => $cycle->id,
                    'old_status' => $cycle->status,
                    'new_status' => $request->status,
                    'changed_by' => auth()->id(),
                    'notes' => 'Status changed via dashboard'
                ];
                $updates['status'] = $request->status;
            }

            if ($request->has('next_followup_date')) {
                $updates['next_followup_date'] = $request->next_followup_date;
            }

            if ($request->has('next_action')) {
                $updates['next_action'] = $request->next_action;
            }

            // Transaction
            DB::beginTransaction();

            if (!empty($updates)) {
                $cycle->update($updates);
            }

            if ($logData) {
                \App\Models\CycleStatusLog::create($logData);
            }

            DB::commit();

            return response()->json([
                'message' => 'Berhasil memperbarui status',
                'data' => $cycle->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update status error', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return response()->json([
                'message' => 'Gagal memperbarui status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all messages with status 'valid' and their images
     */
    public function cleanupValid()
    {
        try {
            DB::beginTransaction();

            // Get valid messages
            $validMessages = Message::where('validation_status', 'valid')->get();
            $count = $validMessages->count();

            if ($count === 0) {
                DB::commit();
                return response()->json([
                    'message' => 'Tidak ada data valid yang perlu dihapus',
                    'deleted_count' => 0
                ]);
            }

            foreach ($validMessages as $message) {
                // Delete image file file if exists
                if ($message->screenshot_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($message->screenshot_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($message->screenshot_path);
                }

                $message->delete();
            }

            DB::commit();

            Log::info('Bulk cleanup valid messages', [
                'deleted_count' => $count,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Berhasil menghapus data valid',
                'deleted_count' => $count
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cleanup valid error', [
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
                },
                'statusLogs.user' // Eager load logs and user who changed them
            ]);

            // ... (filters omitted for brevity if unchanged, but I need to include them to be safe or just show changed part)
            // Wait, I should stick to replacing the whole block or just the part I need.

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
                    'contact_number' => $cycle->prospect->contact_number ?? '-', // New field
                    'category' => $category,
                    'status' => $cycle->status, // ongoing / converted / rejected
                    'current_stage' => $cycle->current_stage,
                    'start_date' => $cycle->start_date->format('Y-m-d'),
                    'last_followup_date' => $cycle->last_followup_date ? $cycle->last_followup_date->format('Y-m-d') : '-',
                    'next_followup_date' => $cycle->next_followup_date ? $cycle->next_followup_date->format('Y-m-d') : '-',
                    'next_action' => $cycle->next_action ?? '-',
                    'stages' => $messagesByStage,
                    'logs' => $cycle->statusLogs->map(function ($log) {
                        return [
                            'date' => $log->created_at->format('Y-m-d H:i'),
                            'old' => $log->old_status,
                            'new' => $log->new_status,
                            'by' => $log->user->name ?? 'System'
                        ];
                    })
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
