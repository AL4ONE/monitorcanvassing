<?php

namespace App\Http\Controllers;

use App\Models\CanvassingCycle;
use App\Models\Message;
use App\Services\MessageTemplateService;
use App\Services\MessageValidationService;
use App\Services\OcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    protected $ocrService;
    protected $validationService;
    protected $templateService;

    public function __construct(
        OcrService $ocrService,
        MessageValidationService $validationService,
        MessageTemplateService $templateService
    ) {
        $this->ocrService = $ocrService;
        $this->validationService = $validationService;
        $this->templateService = $templateService;
    }

    /**
     * Upload screenshot and process
     */
    public function upload(Request $request)
    {
        ini_set('memory_limit', '512M'); // Increase memory limit for image processing and OCR matching

        Log::info('Upload request started', [
            'has_file' => $request->hasFile('screenshot'),
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'unknown',
        ]);

        $request->validate([
            'screenshot' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            'stage' => 'nullable|integer|min:0|max:7',
            'contact_number' => 'nullable|string|max:50',
            'channel' => 'nullable|string|in:instagram,tiktok,facebook,threads,whatsapp,other',
            'interaction_status' => 'nullable|string|in:no_response,menolak,tertarik,menerima',
        ]);

        $user = Auth::user();

        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya staff yang dapat mengupload screenshot',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Store file
            $file = $request->file('screenshot');
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('screenshots', $fileName, 'public');
            $fullPath = storage_path('app/public/' . $filePath);

            // Generate hash
            $fileHash = hash_file('sha256', $fullPath);

            // Get stage from request (required)
            $expectedStage = $request->input('stage');
            if ($expectedStage === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stage harus diisi',
                ], 422);
            }
            $expectedStage = (int) $expectedStage;

            // Get category from request (required)
            $category = $request->input('category');
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori harus diisi',
                ], 422);
            }

            // Validate category
            $validCategories = ['umkm_fb', 'coffee_shop', 'restoran'];
            if (!in_array($category, $validCategories)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak valid',
                ], 422);
            }

            // Validate
            $validation = $this->validationService->validateAndProcess(
                $fullPath,
                $fileHash,
                $user->id,
                $expectedStage
            );

            if (!$validation['valid']) {
                Storage::disk('public')->delete($filePath);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validation['errors'],
                ], 422);
            }

            // Run OCR (pass expected stage to filter messages)
            $ocrResult = $this->ocrService->extractData($fullPath, $expectedStage);

            // Validate message content matches expected stage (ALWAYS validate for stage > 0)
            // This ensures the message contains the correct template for the selected day
            if ($expectedStage > 0 && $ocrResult['message_snippet']) {
                $messageValidation = $this->templateService->validateMessageForStage(
                    $ocrResult['message_snippet'],
                    $expectedStage
                );

                if (!$messageValidation['valid']) {
                    Storage::disk('public')->delete($filePath);
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Pesan tidak sesuai dengan template Day {$expectedStage}. Pesan harus mengandung template yang sesuai dengan Day {$expectedStage}. Detected: " . ($messageValidation['detected_stage'] !== null ? "Day {$messageValidation['detected_stage']}" : "Tidak terdeteksi"),
                    ], 422);
                }
            }

            // Find or create cycle based on OCR result
            if (!$ocrResult['instagram_username']) {
                Storage::disk('public')->delete($filePath);
                DB::rollBack();

                // Log OCR result for debugging with extensive details
                \Illuminate\Support\Facades\Log::error('OCR failed to extract username - returning error to user', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'expected_stage' => $expectedStage,
                    'is_followup' => $expectedStage > 0,
                    'ocr_result' => $ocrResult,
                    'ocr_message_length' => strlen($ocrResult['message_snippet'] ?? ''),
                    'ocr_date' => $ocrResult['date'],
                    'file_path' => $filePath,
                    'note' => 'Check Railway logs for detailed OCR parsing logs (header area, patterns tried, potential usernames)',
                ]);

                // Always include debug info in response (not just when APP_DEBUG=true)
                // This helps debugging OCR issues in production
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mendeteksi username Instagram. Pastikan: 
                    1. Username terlihat di bagian ATAS screenshot. 
                    2. Tidak tertutup notifikasi. 
                    3. Format screenshot jelas.
                    
                    Text terdeteksi (Header): "' . substr($ocrResult['message_snippet'] ?? '', 0, 100) . '..."',
                    'debug' => [
                        'ocr_date' => $ocrResult['date'],
                        'expected_stage' => $expectedStage,
                        'is_followup' => $expectedStage > 0,
                    ],
                ], 422);
            }

            $cycleResult = $this->validationService->findOrCreateCycle(
                $ocrResult['instagram_username'],
                $user->id,
                $expectedStage
            );

            if (!$cycleResult['valid']) {
                Storage::disk('public')->delete($filePath);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $cycleResult['error'],
                ], 422);
            }

            // Create message record
            $message = Message::create([
                'canvassing_cycle_id' => $cycleResult['cycle']->id,
                'stage' => $expectedStage,
                'category' => $category,
                'channel' => $request->input('channel'),
                'interaction_status' => $request->input('interaction_status'),
                'screenshot_path' => $filePath,
                'screenshot_hash' => $fileHash,
                'ocr_instagram_username' => $ocrResult['instagram_username'],
                'ocr_message_snippet' => $ocrResult['message_snippet'],
                'ocr_date' => $ocrResult['date'],
                'submitted_at' => now(),
                'validation_status' => 'pending',
            ]);

            // Update cycle current_stage and last_followup_date
            $cycleUpdates = [
                'current_stage' => $expectedStage,
                'last_followup_date' => now(), // Update last interaction date
            ];

            // Update cycle status based on staff interaction feedback
            if ($request->filled('interaction_status')) {
                $status = $request->interaction_status;
                if ($status === 'menolak') {
                    $cycleUpdates['status'] = 'rejected';
                    $cycleUpdates['failure_reason'] = 'Menolak (Staff Input)';
                } elseif ($status === 'menerima') {
                    $cycleUpdates['status'] = 'converted';
                } elseif ($status === 'tertarik') {
                    $cycleUpdates['status'] = 'ongoing';
                } elseif ($status === 'no_response') {
                    // Force ongoing if no response but was active, or keep valid status
                    $cycleResult['cycle']->status === 'active' ? $cycleUpdates['status'] = 'ongoing' : null;
                }
            }

            // If contact number valid and prospect exists, update it
            if ($request->filled('contact_number') && $cycleResult['cycle']->prospect) {
                $cycleResult['cycle']->prospect->update([
                    'contact_number' => $request->contact_number
                ]);
            }

            $cycleResult['cycle']->update($cycleUpdates);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Screenshot berhasil diupload',
                'data' => [
                    'id' => $message->id,
                    'stage' => $message->stage,
                    'ocr_result' => $ocrResult,
                    'validation_status' => $message->validation_status,
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            if (isset($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            \Illuminate\Support\Facades\Log::error('Upload failed (Fatal): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? 'unknown',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem (Server Error)',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Get messages for current staff
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Message::with(['canvassingCycle.prospect', 'canvassingCycle.staff'])
            ->whereHas('canvassingCycle', function ($q) use ($user) {
                if ($user->role === 'staff') {
                    $q->where('staff_id', $user->id);
                }
            });

        if ($request->has('date')) {
            $query->whereDate('submitted_at', $request->date);
        }

        if ($request->has('stage')) {
            $query->where('stage', $request->stage);
        }

        if ($request->has('validation_status')) {
            $query->where('validation_status', $request->validation_status);
        }

        $messages = $query->orderBy('submitted_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($messages);
    }

    /**
     * Get message detail
     */
    public function show($id)
    {
        $user = Auth::user();

        $message = Message::with(['canvassingCycle.prospect', 'canvassingCycle.staff', 'qualityCheck.supervisor'])
            ->whereHas('canvassingCycle', function ($q) use ($user) {
                if ($user->role === 'staff') {
                    $q->where('staff_id', $user->id);
                }
            })
            ->findOrFail($id);

        return response()->json([
            'data' => $message,
            'screenshot_url' => url('storage/' . $message->screenshot_path),
        ]);
    }

    /**
     * Delete message (only for staff who created it, and only if pending)
     */
    public function destroy($id)
    {
        $user = Auth::user();

        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya staff yang dapat menghapus message',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $message = Message::with('canvassingCycle')
                ->whereHas('canvassingCycle', function ($q) use ($user) {
                    $q->where('staff_id', $user->id);
                })
                ->findOrFail($id);

            // Only allow delete if status is pending
            if ($message->validation_status !== 'pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus message yang sudah direview oleh supervisor',
                ], 422);
            }

            // Check if there are follow-ups after this message
            $hasFollowUps = Message::where('canvassing_cycle_id', $message->canvassing_cycle_id)
                ->where('stage', '>', $message->stage)
                ->exists();

            if ($hasFollowUps) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus message karena sudah ada follow-up setelahnya',
                ], 422);
            }

            // Delete screenshot file
            if ($message->screenshot_path && Storage::disk('public')->exists($message->screenshot_path)) {
                Storage::disk('public')->delete($message->screenshot_path);
            }

            // Get cycle info before deletion
            $cycle = $message->canvassingCycle;
            $stage = $message->stage;

            // Delete message
            $message->delete();

            // Update cycle current_stage if this was the latest message
            $maxStage = Message::where('canvassing_cycle_id', $cycle->id)->max('stage');
            $cycle->update([
                'current_stage' => $maxStage ?? -1,
            ]);

            // If this was canvassing (stage 0) and no other messages, delete the cycle and prospect
            if ($stage === 0 && Message::where('canvassing_cycle_id', $cycle->id)->count() === 0) {
                $prospect = $cycle->prospect;
                $cycle->delete();

                // Only delete prospect if no other cycles exist for it
                if ($prospect && CanvassingCycle::where('prospect_id', $prospect->id)->count() === 0) {
                    $prospect->delete();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Message berhasil dihapus',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus message',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

