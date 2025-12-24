<?php

namespace App\Services;

use App\Models\CanvassingCycle;
use App\Models\Message;
use App\Models\Prospect;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class MessageValidationService
{
    /**
     * Validate and process uploaded screenshot
     */
    public function validateAndProcess(
        string $filePath,
        string $fileHash,
        int $staffId,
        ?int $expectedStage = null
    ): array {
        $errors = [];
        $warnings = [];

        // 1. Check duplicate hash
        $existingMessage = Message::where('screenshot_hash', $fileHash)->first();
        if ($existingMessage) {
            return [
                'valid' => false,
                'errors' => ['File ini sudah pernah diupload sebelumnya'],
                'warnings' => [],
            ];
        }

        // 2. Determine stage if not provided
        if ($expectedStage === null) {
            $expectedStage = $this->determineExpectedStage($staffId);
        }

        // 3. Validate continuity for follow-ups
        if ($expectedStage > 0) {
            $continuityCheck = $this->checkContinuity($staffId, $expectedStage);
            if (!$continuityCheck['valid']) {
                $errors = array_merge($errors, $continuityCheck['errors']);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'expected_stage' => $expectedStage,
        ];
    }

    /**
     * Determine expected stage based on staff's current progress
     */
    public function determineExpectedStage(int $staffId): int
    {
        $today = Carbon::today();

        // Check if there are active cycles that need follow-up
        $activeCycles = CanvassingCycle::where('staff_id', $staffId)
            ->where('status', 'active')
            ->with('messages')
            ->get();

        foreach ($activeCycles as $cycle) {
            $maxStage = $cycle->messages()->max('stage') ?? -1;
            $lastMessage = $cycle->messages()->orderBy('submitted_at', 'desc')->first();

            if ($lastMessage && $maxStage < 7) {
                // Check if last message was yesterday (for follow-up)
                $lastMessageDate = Carbon::parse($lastMessage->submitted_at)->startOfDay();
                $yesterday = Carbon::yesterday();

                if ($lastMessageDate->equalTo($yesterday) && $maxStage < 7) {
                    return $maxStage + 1;
                }
            }
        }

        // If no follow-up needed, return 0 for new canvassing
        return 0;
    }

    /**
     * Check continuity - ensure follow-up is for existing prospect
     */
    private function checkContinuity(int $staffId, int $stage): array
    {
        $errors = [];

        if ($stage <= 0) {
            return ['valid' => true, 'errors' => []];
        }

        // Check if previous stage exists for this staff
        $previousStageCount = Message::whereHas('canvassingCycle', function($q) use ($staffId) {
            $q->where('staff_id', $staffId);
        })
        ->where('stage', $stage - 1)
        ->count();

        if ($previousStageCount === 0) {
            $errors[] = "Follow-up stage {$stage} tidak valid. Pastikan sudah upload " . ($stage === 1 ? 'Canvassing' : "Follow Up " . ($stage - 1)) . " terlebih dahulu.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Find or create prospect and cycle based on Instagram username
     * Uses partial matching to handle truncated usernames from header
     */
    public function findOrCreateCycle(string $instagramUsername, int $staffId, int $stage): array
    {
        $instagramUsername = strtolower(trim($instagramUsername));
        // Remove trailing dots/underscores that might indicate truncation
        $instagramUsername = rtrim($instagramUsername, '._');

        \Illuminate\Support\Facades\Log::info('findOrCreateCycle called', [
            'instagram_username' => $instagramUsername,
            'staff_id' => $staffId,
            'stage' => $stage,
        ]);

        // First try exact match
        $prospect = Prospect::where('instagram_username', $instagramUsername)->first();

        if ($prospect) {
            \Illuminate\Support\Facades\Log::info('Found prospect by exact match', [
                'prospect_id' => $prospect->id,
                'stored_username' => $prospect->instagram_username,
            ]);
        }

        // If not found, try partial matching (handle truncated usernames)
        // e.g., "bebekcaberawit_grandwis" should match "bebekcaberawit_grandwisata"
        // or "bebekcaberawit_grandwisata" should match "bebekcaberawit_grandwis"
        if (!$prospect) {
            // Get base username (first part before potential truncation)
            // Username format usually: "prefix_suffix" - match on prefix part
            $baseParts = explode('_', $instagramUsername);
            $basePrefix = $baseParts[0]; // e.g., "bebekcaberawit"
            $hasSuffix = count($baseParts) > 1;

            \Illuminate\Support\Facades\Log::info('Trying partial matching', [
                'base_prefix' => $basePrefix,
                'has_suffix' => $hasSuffix,
            ]);

            // Try to find prospect where:
            // 1. Stored username starts with extracted username (extracted is truncated)
            // 2. Extracted username starts with stored username (stored is truncated)
            // 3. Both start with same prefix (handle truncation in suffix part)
            $prospect = Prospect::where(function($query) use ($instagramUsername, $basePrefix, $hasSuffix) {
                // Case 1: Extracted is truncated, stored is full
                // e.g., extracted: "bebekcaberawit_grandwis", stored: "bebekcaberawit_grandwisata"
                $query->where('instagram_username', 'like', $instagramUsername . '%');

                // Case 2: Stored is truncated, extracted is full
                // e.g., extracted: "bebekcaberawit_grandwisata", stored: "bebekcaberawit_grandwis"
                // Use SQLite-compatible syntax (|| instead of CONCAT)
                $query->orWhere(function($q) use ($instagramUsername) {
                    $dbDriver = DB::connection()->getDriverName();
                    if ($dbDriver === 'sqlite') {
                        // SQLite: use || for concatenation
                        $q->whereRaw('? LIKE (instagram_username || \'%\')', [$instagramUsername]);
                    } else {
                        // MySQL/PostgreSQL: use CONCAT
                        $q->whereRaw('? LIKE CONCAT(instagram_username, \'%\')', [$instagramUsername]);
                    }
                });

                // Case 3: Both have same prefix (match on prefix part)
                // e.g., extracted: "bebekcaberawit_grandwis", stored: "bebekcaberawit_grandwisata"
                if ($hasSuffix) {
                    $query->orWhere('instagram_username', 'like', $basePrefix . '_%');
                }
            })->first();

            if ($prospect) {
                \Illuminate\Support\Facades\Log::info('Found prospect by partial match', [
                    'prospect_id' => $prospect->id,
                    'stored_username' => $prospect->instagram_username,
                    'extracted_username' => $instagramUsername,
                ]);
            }
        }

        // If still not found and this is a follow-up, try searching by ocr_instagram_username from previous messages
        // This handles cases where OCR extracts different usernames but they're actually the same prospect
        if (!$prospect && $stage > 0) {
            \Illuminate\Support\Facades\Log::info('Trying to find by OCR username from previous messages', [
                'extracted_username' => $instagramUsername,
                'staff_id' => $staffId,
            ]);

            // Find messages from this staff with matching OCR username (with partial matching)
            $matchingMessages = Message::whereHas('canvassingCycle', function($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            })
            ->where(function($q) use ($instagramUsername) {
                // Exact match
                $q->where('ocr_instagram_username', $instagramUsername)
                // Partial match: stored OCR username starts with extracted
                ->orWhere('ocr_instagram_username', 'like', $instagramUsername . '%')
                // Partial match: extracted starts with stored OCR username
                ->orWhere(function($q2) use ($instagramUsername) {
                    $dbDriver = DB::connection()->getDriverName();
                    if ($dbDriver === 'sqlite') {
                        $q2->whereRaw('? LIKE (ocr_instagram_username || \'%\')', [$instagramUsername]);
                    } else {
                        $q2->whereRaw('? LIKE CONCAT(ocr_instagram_username, \'%\')', [$instagramUsername]);
                    }
                });
            })
            ->with('canvassingCycle.prospect')
            ->get();

            if ($matchingMessages->isNotEmpty()) {
                // Get the prospect from the first matching message
                $firstMessage = $matchingMessages->first();
                if ($firstMessage->canvassingCycle && $firstMessage->canvassingCycle->prospect) {
                    $prospect = $firstMessage->canvassingCycle->prospect;

                    // Update prospect username if extracted username is longer (less truncated)
                    if (strlen($instagramUsername) > strlen($prospect->instagram_username)) {
                        $prospect->update(['instagram_username' => $instagramUsername]);
                        \Illuminate\Support\Facades\Log::info('Updated prospect username to longer version', [
                            'prospect_id' => $prospect->id,
                            'old_username' => $prospect->getOriginal('instagram_username'),
                            'new_username' => $instagramUsername,
                        ]);
                    }

                    \Illuminate\Support\Facades\Log::info('Found prospect via OCR username from previous message', [
                        'prospect_id' => $prospect->id,
                        'stored_username' => $prospect->instagram_username,
                        'previous_ocr_username' => $firstMessage->ocr_instagram_username,
                        'current_ocr_username' => $instagramUsername,
                    ]);
                }
            }
        }

        if ($stage == 0) {
            // Canvassing - create new prospect if doesn't exist
            if (!$prospect) {
                // Use the extracted username (might be truncated, that's OK)
                $prospect = Prospect::create([
                    'instagram_username' => $instagramUsername,
                ]);
            } else {
                // If prospect exists but username is different (one is truncated), update to longer version
                if (strlen($prospect->instagram_username) < strlen($instagramUsername)) {
                    $prospect->update(['instagram_username' => $instagramUsername]);
                }
                // If extracted is shorter (truncated), keep the longer one (no update needed)
            }

            // Check if cycle already exists for this staff
            $existingCycle = CanvassingCycle::where('prospect_id', $prospect->id)
                ->where('staff_id', $staffId)
                ->where('status', 'active')
                ->first();

            if ($existingCycle) {
                return [
                    'valid' => false,
                    'error' => 'Prospect ini sudah pernah di-canvassing sebelumnya',
                    'cycle' => null,
                ];
            }

            $cycle = CanvassingCycle::create([
                'prospect_id' => $prospect->id,
                'staff_id' => $staffId,
                'start_date' => Carbon::today(),
                'current_stage' => 0,
                'status' => 'active',
            ]);

            return [
                'valid' => true,
                'cycle' => $cycle,
            ];
        } else {
            // Follow-up - must find existing cycle
            if (!$prospect) {
                return [
                    'valid' => false,
                    'error' => 'Prospect tidak ditemukan. Follow-up harus untuk prospect yang sudah di-canvassing.',
                    'cycle' => null,
                ];
            }

            $cycle = CanvassingCycle::where('prospect_id', $prospect->id)
                ->where('staff_id', $staffId)
                ->where('status', 'active')
                ->first();

            if (!$cycle) {
                return [
                    'valid' => false,
                    'error' => 'Cycle tidak ditemukan untuk follow-up ini',
                    'cycle' => null,
                ];
            }

            // Check if previous stage exists
            $previousMessage = Message::where('canvassing_cycle_id', $cycle->id)
                ->where('stage', $stage - 1)
                ->first();

            if (!$previousMessage) {
                return [
                    'valid' => false,
                    'error' => "Follow-up stage {$stage} tidak valid. Stage sebelumnya belum ada.",
                    'cycle' => null,
                ];
            }

            return [
                'valid' => true,
                'cycle' => $cycle,
            ];
        }
    }

    /**
     * Check daily targets
     */
    public function checkDailyTargets(int $staffId, string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $target = 50;

        $canvassingCount = Message::whereHas('canvassingCycle', function($query) use ($staffId) {
            $query->where('staff_id', $staffId);
        })
        ->where('stage', 0)
        ->whereDate('submitted_at', $date)
        ->count();

        $followUpCount = Message::whereHas('canvassingCycle', function($query) use ($staffId) {
            $query->where('staff_id', $staffId);
        })
        ->where('stage', '>', 0)
        ->whereDate('submitted_at', $date)
        ->count();

        return [
            'date' => $date->format('Y-m-d'),
            'canvassing' => [
                'count' => $canvassingCount,
                'target' => $target,
                'met' => $canvassingCount >= $target,
            ],
            'follow_up' => [
                'count' => $followUpCount,
                'target' => $target,
                'met' => $followUpCount >= $target,
            ],
        ];
    }
}

