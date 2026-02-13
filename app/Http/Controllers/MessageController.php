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

        if ($request->hasFile('screenshot')) {
            $file = $request->file('screenshot');
            Log::info('File details:', [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $file->getClientOriginalExtension(),
            ]);
        } else {
            Log::warning('No screenshot file in request');
        }

        $request->validate([
            'screenshot' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:20480', // 20MB max
            'stage' => 'nullable|integer|min:0|max:7',
            'contact_number' => 'nullable|string|max:50',
            'instagram_link' => 'nullable|url|max:255',
            'channel' => 'nullable|string|max:255',
            'interaction_status' => 'nullable|string|in:no_response,menolak,tertarik,menerima',
            'lokasi' => 'nullable|string|max:255',
            'prospect_id' => 'nullable|integer|exists:prospects,id', // Manual prospect selection for follow-up when OCR fails
            'channel_category' => 'nullable|string|max:255',
            'has_website' => 'boolean',
            'website_url' => 'nullable|required_if:has_website,true|url|max:255',
            'has_payment_gateway' => 'boolean',
        ]);

        $user = Auth::user();

        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya staff yang dapat mengupload screenshot',
            ], 403);
        }

        // Temporary test bypass: allow skipping OCR + validation when enabled via config
        $bypass = config('app.allow_fake_ocr') && config('app.env') !== 'production';

        try {
            DB::beginTransaction();

            // Store file
            $file = $request->file('screenshot');
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $disk = config('filesystems.default');
            // Generate hash from temp file before moving
            $tempPath = $file->getRealPath();
            $fileHash = hash_file('sha256', $tempPath);
            // Save to configured disk with public visibility
            $filePath = $file->storeAs('screenshots', $fileName, [
                'disk' => $disk,
                'visibility' => 'public',
            ]);

            // Hash sudah dihitung dari temp file

            // Get stage from request (required)
            $expectedStage = $request->input('stage');
            if ($expectedStage === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stage harus diisi',
                ], 422);
            }
            $expectedStage = (int) $expectedStage;

            // Get category from request (required only for Day 0)
            $category = $request->input('category');
            if ($expectedStage === 0 && !$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori harus diisi untuk Canvassing Day 0',
                ], 422);
            }

            // Validate category (if provided)
            $validCategories = ['umkm_fb', 'coffee_shop', 'restoran', 'product_digital'];
            if ($category && !in_array($category, $validCategories)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kategori tidak valid',
                ], 422);
            }

            // Validate (bypass for testing when enabled)
            if ($bypass) {
                $validation = ['valid' => true];
            } else {
                $validation = $this->validationService->validateAndProcess(
                    $tempPath,
                    $fileHash,
                    $user->id,
                    $expectedStage
                );
            }

            if (!$validation['valid']) {
                Storage::disk($disk)->delete($filePath);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validation['errors'],
                ], 422);
            }

            // Run OCR (or bypass with fake data)
            if ($bypass) {
                $ocrResult = [
                    'instagram_username' => 'test_user_' . substr($fileHash, 0, 8),
                    'message_snippet' => 'Bypass message for testing',
                    'date' => now()->toDateString(),
                ];
            } else {
                // Split & Conquer Strategy (Smart Fallback): 
                // 1. Try 'header_crop' first (High Quality, Top Only)
                // 2. If valid username found -> Done.
                // 3. If NOT found -> Fallback to stored 'screenshot' (Full Image)

                $ocrResult = [
                    'instagram_username' => null,
                    'message_snippet' => null,
                    'date' => null,
                    'raw_text' => null
                ];
                $attemptedCrop = false;

                // --- ATTEMPT 1: Header Crop ---
                if ($request->hasFile('header_crop')) {
                    $attemptedCrop = true;
                    $ocrFile = $request->file('header_crop');
                    Log::info('Attempting OCR 1/2: Helper Crop', [
                        'size' => $ocrFile->getSize(),
                        'mime' => $ocrFile->getMimeType()
                    ]);

                    $tempOcrPath = $ocrFile->getRealPath();
                    // Extract Date
                    $ocrResult = $this->ocrService->extractData($tempOcrPath, $expectedStage);
                }

                // --- ATTEMPT 2: Full Screenshot (Fallback) ---
                // Run if: (1) No crop provided OR (2) Crop provided but yielded NO USERNAME
                if (!$attemptedCrop || empty($ocrResult['instagram_username'])) {
                    try {
                        if ($attemptedCrop) {
                            Log::warning('OCR Attempt 1 (Crop) Failed - Retrying with Full Screenshot...', [
                                'prev_result' => $ocrResult['instagram_username'] ?? 'NULL',
                                'prev_raw' => substr($ocrResult['raw_text'] ?? '', 0, 100)
                            ]);
                        } else {
                            Log::info('No Header Crop provided - Using Full Screenshot directly.');
                        }

                        // Prepare Full Screenshot Path
                        $fullImagePath = null;
                        $isS3Temp = false;

                        if ($disk === 's3' || $disk === 'minio') {
                            // FIX: Ensure temp file has valid extension (JPG) so OCR service can detect it
                            // Previously: $fileName . '_full' -> caused "File failed validation" or text/plain mime type
                            $fullImagePath = sys_get_temp_dir() . '/' . $fileName . '_full.jpg';
                            file_put_contents($fullImagePath, Storage::disk($disk)->get($filePath));
                            $isS3Temp = true;
                            Log::info('Downloaded S3 file for OCR Retry', ['temp_path' => $fullImagePath]);
                        } else {
                            $fullImagePath = Storage::disk($disk)->path($filePath);
                        }

                        // Run OCR on Full Image
                        $retryResult = $this->ocrService->extractData($fullImagePath, $expectedStage);

                        // Cleanup S3 temp file
                        if ($isS3Temp && file_exists($fullImagePath)) {
                            unlink($fullImagePath);
                        }

                        // Use retry result if it found something
                        if (!empty($retryResult['instagram_username'])) {
                            Log::info('OCR Retry SUCCESS', ['username' => $retryResult['instagram_username']]);
                            $ocrResult = $retryResult;
                        } else {
                            Log::warning('OCR Retry FAILED', ['raw_text' => substr($retryResult['raw_text'] ?? '', 0, 100)]);
                            // Append retry raw text to original raw text for debugging
                            if (isset($ocrResult['raw_text'])) {
                                $ocrResult['raw_text'] .= "\n[RETRY LOG]: " . ($retryResult['raw_text'] ?? 'NULL');
                            } else {
                                $ocrResult = $retryResult; // Use retry result even if empty
                            }
                        }

                    } catch (\Exception $e) {
                        Log::error('OCR Retry Crashed: ' . $e->getMessage());
                        // Don't crash the whole request, just log and keep original error
                        if (isset($ocrResult['raw_text'])) {
                            $ocrResult['raw_text'] .= "\n[RETRY ERROR]: " . $e->getMessage();
                        }
                    }
                }


            }

            // Optional bypass for testing when no username is detected (e.g., dummy images)
            if (empty($ocrResult['instagram_username'])) {
                $bypassUsername = config('services.ocr_space.bypass_username');
                if (!empty($bypassUsername)) {
                    $ocrResult['instagram_username'] = $bypassUsername;
                    $ocrResult['message_snippet'] = $ocrResult['message_snippet'] ?? 'bypass-message';
                    Log::warning('OCR bypass username applied', [
                        'bypass_username' => $bypassUsername,
                        'expected_stage' => $expectedStage,
                        'user_id' => $user->id,
                    ]);
                }
            }

            // Validate message content matches expected stage (ALWAYS validate for stage > 0)
            // This ensures the message contains the correct template for the selected day
            if (!$bypass && $expectedStage > 0 && $ocrResult['message_snippet']) {
                $messageValidation = $this->templateService->validateMessageForStage(
                    $ocrResult['message_snippet'],
                    $expectedStage
                );

                if (!$messageValidation['valid']) {
                    Storage::disk($disk)->delete($filePath);
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Pesan tidak sesuai dengan template Day {$expectedStage}. Pesan harus mengandung template yang sesuai dengan Day {$expectedStage}. Detected: " . ($messageValidation['detected_stage'] !== null ? "Day {$messageValidation['detected_stage']}" : "Tidak terdeteksi"),
                    ], 422);
                }
            }

            // Find or create cycle based on OCR result OR manual prospect selection OR manual username
            $manualProspectId = $request->input('prospect_id');
            $manualUsername = $request->input('manual_username');

            // If OCR failed but manual username was provided (Day 0 fallback), use it
            if (!$ocrResult['instagram_username'] && $manualUsername && $expectedStage === 0) {
                $ocrResult['instagram_username'] = strtolower(trim($manualUsername));
                Log::info('Using manual username for Day 0 canvassing', [
                    'manual_username' => $manualUsername,
                    'user_id' => $user->id,
                ]);
            }

            if (!$bypass && !$ocrResult['instagram_username'] && !$manualProspectId) {
                Storage::disk($disk)->delete($filePath);
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
                Log::warning('OCR Failed to extract username', ['ocr_result' => $ocrResult]);

                return response()->json([
                    'success' => false,
                    'message' => '[DEBUG-LIVE] Gagal mendeteksi username Instagram. \n\nRAW TEXT DR OCR: \n' . ($ocrResult['raw_text'] ?? $ocrResult['message_snippet'] ?? 'KOSONG (Gak ada teks terbaca)'),
                    'debug' => [
                        'ocr_date' => $ocrResult['date'],
                        'expected_stage' => $expectedStage,
                        'is_followup' => $expectedStage > 0,
                        'raw_text_preview' => substr($ocrResult['message_snippet'] ?? '', 0, 500)
                    ],
                    'show_manual_selection' => true,
                ], 422);
            }

            // Use manual prospect selection (bypass OCR) OR OCR result
            if ($manualProspectId && $expectedStage > 0) {
                // Manual selection for follow-up
                $prospect = \App\Models\Prospect::find($manualProspectId);
                if (!$prospect) {
                    Storage::disk($disk)->delete($filePath);
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Prospect tidak ditemukan',
                    ], 422);
                }

                // Find active cycle for this prospect and staff
                $cycle = CanvassingCycle::where('prospect_id', $prospect->id)
                    ->where('staff_id', $user->id)
                    ->whereIn('status', ['active', 'ongoing', 'sedang berlangsung'])
                    ->first();

                if (!$cycle) {
                    Storage::disk($disk)->delete($filePath);
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak ditemukan siklus canvassing aktif untuk prospect ini.',
                    ], 422);
                }

                // Check previous stage exists
                $previousMessage = Message::where('canvassing_cycle_id', $cycle->id)
                    ->where('stage', $expectedStage - 1)
                    ->first();

                if (!$previousMessage) {
                    Storage::disk($disk)->delete($filePath);
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Follow-up stage {$expectedStage} tidak valid. Stage sebelumnya belum ada.",
                    ], 422);
                }

                $cycleResult = [
                    'valid' => true,
                    'cycle' => $cycle,
                ];

                Log::info('Using manual prospect selection for follow-up', [
                    'prospect_id' => $manualProspectId,
                    'prospect_username' => $prospect->instagram_username,
                    'cycle_id' => $cycle->id,
                    'stage' => $expectedStage,
                ]);

                // Set username for message record (from prospect, not OCR)
                $ocrResult['instagram_username'] = $prospect->instagram_username;
            } else {
                // Normal flow: use OCR result
                $cycleResult = $this->validationService->findOrCreateCycle(
                    $ocrResult['instagram_username'],
                    $user->id,
                    $expectedStage
                );
            }

            if (!$cycleResult['valid']) {
                Storage::disk($disk)->delete($filePath);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $cycleResult['error'],
                ], 422);
            }

            // If follow-up (stage > 0) and fields are missing, inherit from Day 0 message
            if ($expectedStage > 0) {
                // Find Day 0 message for this cycle to copy data
                $day0Message = Message::where('canvassing_cycle_id', $cycleResult['cycle']->id)
                    ->where('stage', 0)
                    ->first();

                if ($day0Message) {
                    if (!$category)
                        $category = $day0Message->category;
                    if (!$request->input('channel'))
                        $request->merge(['channel' => $day0Message->channel]);
                    if (!$request->input('channel_category'))
                        $request->merge(['channel_category' => $day0Message->channel_category]);

                    // Also inherit website/payment info if not provided (though checkboxes usually send false if unchecked, strict check logic here)
                    // Logic: If request doesn't have the field at all. But checkboxes usually present.
                    // Let's assume frontend sends them as false/null. We can copy if they are "false" in request but true in Day 0? 
                    // Better to just stick to Category/Channel for now as those are the main grouping keys.
                }
            }

            // Create message record
            $message = Message::create([
                'canvassing_cycle_id' => $cycleResult['cycle']->id,
                'stage' => $expectedStage,
                'category' => $category,
                'channel' => $request->input('channel'),
                'channel_category' => $request->input('channel_category'),
                'has_website' => $request->boolean('has_website'),
                'website_url' => $request->input('website_url'),
                'has_payment_gateway' => $request->boolean('has_payment_gateway'),
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

            // If contact number, instagram link, or lokasi provided and prospect exists, update it
            if (($request->filled('contact_number') || $request->filled('instagram_link') || $request->filled('lokasi')) && $cycleResult['cycle']->prospect) {
                $updateData = [];
                if ($request->filled('contact_number')) {
                    $updateData['contact_number'] = $request->contact_number;
                }
                if ($request->filled('instagram_link')) {
                    $updateData['instagram_link'] = $request->instagram_link;
                }
                if ($request->filled('lokasi')) {
                    $updateData['lokasi'] = $request->lokasi;
                }
                $cycleResult['cycle']->prospect->update($updateData);
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
                $disk = $disk ?? config('filesystems.default');
                Storage::disk($disk)->delete($filePath);
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

        $disk = config('filesystems.default');
        if ($disk === 's3') {
            // Generate public URL using config or fallback to manual build
            $baseUrl = config('filesystems.disks.s3.url');
            if (!empty($baseUrl)) {
                // Use AWS_URL from config
                $screenshotUrl = rtrim($baseUrl, '/') . '/' . $message->screenshot_path;
            } else {
                // Fallback: build URL manually from endpoint + bucket
                $endpoint = config('filesystems.disks.s3.endpoint');
                $bucket = config('filesystems.disks.s3.bucket');
                $screenshotUrl = rtrim($endpoint, '/') . '/' . $bucket . '/' . $message->screenshot_path;
            }
        } else {
            $screenshotUrl = url('storage/' . $message->screenshot_path);
        }

        return response()->json([
            'data' => $message,
            'screenshot_url' => $screenshotUrl,
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

            // Delete screenshot file on the configured disk
            $disk = config('filesystems.default');
            if ($message->screenshot_path && Storage::disk($disk)->exists($message->screenshot_path)) {
                Storage::disk($disk)->delete($message->screenshot_path);
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

    /**
     * Get active prospects for current staff user (for manual follow-up selection)
     */
    public function getActiveProspects()
    {
        $user = Auth::user();

        if ($user->role !== 'staff') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya staff yang dapat mengakses fitur ini',
            ], 403);
        }

        // Get all active cycles for this staff with prospect data
        $activeCycles = CanvassingCycle::where('staff_id', $user->id)
            ->whereIn('status', ['active', 'ongoing', 'sedang berlangsung'])
            ->with('prospect')
            ->get();

        $prospects = $activeCycles->map(function ($cycle) {
            return [
                'id' => $cycle->prospect->id,
                'instagram_username' => $cycle->prospect->instagram_username,
                'cycle_id' => $cycle->id,
                'current_stage' => $cycle->current_stage,
            ];
        })->unique('id')->values();

        return response()->json([
            'success' => true,
            'data' => $prospects,
        ]);
    }
}

