<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\QualityCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QualityCheckController extends Controller
{
    /**
     * Get pending messages for quality check
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat melakukan quality check',
            ], 403);
        }

        $query = Message::with(['canvassingCycle.prospect', 'canvassingCycle.staff', 'qualityCheck'])
            ->where('validation_status', 'pending');

        // Filter by staff_id
        if ($request->has('staff_id')) {
            $query->whereHas('canvassingCycle', function($q) use ($request) {
                $q->where('staff_id', $request->staff_id);
            });
        }

        // Filter by stage (0 = Canvassing, 1-7 = Follow Up)
        if ($request->has('stage') && $request->stage !== '') {
            $query->where('stage', $request->stage);
        }

        // Filter by Instagram username
        if ($request->has('username') && $request->username !== '') {
            $username = strtolower(trim($request->username));
            $query->whereHas('canvassingCycle.prospect', function($q) use ($username) {
                $q->where('instagram_username', 'like', '%' . $username . '%');
            })->orWhere('ocr_instagram_username', 'like', '%' . $username . '%');
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from !== '') {
            $query->whereDate('submitted_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to !== '') {
            $query->whereDate('submitted_at', '<=', $request->date_to);
        }

        // Filter by category
        if ($request->has('category') && $request->category !== '') {
            $query->where('category', $request->category);
        }

        $messages = $query->orderBy('submitted_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Approve or reject a message
     */
    public function review(Request $request, $id)
    {
        $user = Auth::user();
        
        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat melakukan quality check',
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:1000',
        ]);

        $message = Message::with(['canvassingCycle'])->findOrFail($id);

        // Check if already reviewed
        if ($message->qualityCheck) {
            return response()->json([
                'success' => false,
                'message' => 'Message ini sudah direview sebelumnya',
            ], 422);
        }

        // Create quality check
        $qualityCheck = QualityCheck::create([
            'message_id' => $message->id,
            'supervisor_id' => $user->id,
            'status' => $request->status,
            'notes' => $request->notes,
        ]);

        // Update message validation status
        $message->update([
            'validation_status' => $request->status === 'approved' ? 'valid' : 'invalid',
            'invalid_reason' => $request->status === 'rejected' ? $request->notes : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quality check berhasil disimpan',
            'data' => $qualityCheck->load('supervisor'),
        ]);
    }

    /**
     * Get message detail for quality check
     */
    public function show($id)
    {
        $user = Auth::user();
        
        if ($user->role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya supervisor yang dapat melakukan quality check',
            ], 403);
        }

        $message = Message::with([
            'canvassingCycle.prospect',
            'canvassingCycle.staff',
            'canvassingCycle.messages' => function($q) {
                $q->orderBy('stage', 'asc');
            },
            'qualityCheck.supervisor',
        ])->findOrFail($id);

        // Generate full URL for screenshot
        $screenshotUrl = $message->screenshot_path 
            ? url('storage/' . $message->screenshot_path)
            : null;

        return response()->json([
            'data' => $message,
            'screenshot_url' => $screenshotUrl,
        ]);
    }
}

