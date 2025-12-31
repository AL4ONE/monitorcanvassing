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
            ->whereIn('status', ['active', 'ongoing', 'sedang berlangsung'])
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
        $previousStageCount = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
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

        // Aggressively remove ANY non-alphanumeric characters from the end
        // This handles standard dots (.), OCR noise, or invalid trailing characters
        // Instagram usernames cannot end with a dot anyway.
        $instagramUsername = preg_replace('/[^a-z0-9]+$/', '', $instagramUsername);

        \Illuminate\Support\Facades\Log::info('findOrCreateCycle called (Cleaned)', [
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
        // ONLY for follow-ups - canvassing should create new prospects
        // e.g., "bebekcaberawit_grandwis" should match "bebekcaberawit_grandwisata"
        // or "bebekcaberawit_grandwisata" should match "bebekcaberawit_grandwis"
        if (!$prospect && $stage > 0) {
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
            $prospect = Prospect::where(function ($query) use ($instagramUsername, $basePrefix, $hasSuffix) {
                // Case 1: Extracted is truncated, stored is full
                // e.g., extracted: "bebekcaberawit_grandwis", stored: "bebekcaberawit_grandwisata"
                $query->where('instagram_username', 'like', $instagramUsername . '%');

                // Case 2: Stored is truncated, extracted is full
                // e.g., extracted: "bebekcaberawit_grandwisata", stored: "bebekcaberawit_grandwis"
                // Use SQLite-compatible syntax (|| instead of CONCAT)
                $query->orWhere(function ($q) use ($instagramUsername) {
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
                'stage' => $stage,
            ]);

            // Get base prefix for more aggressive matching
            $baseParts = explode('_', $instagramUsername);
            $basePrefix = $baseParts[0]; // e.g., "nasitimayamsizi"
            $hasSuffix = count($baseParts) > 1;
            $suffixPrefix = $hasSuffix ? substr($baseParts[1], 0, 5) : ''; // First 5 chars of suffix

            // Find messages from this staff with matching OCR username (with aggressive partial matching)
            // First, get all messages from this staff to see what we have
            $allStaffMessages = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            })
                ->with('canvassingCycle.prospect')
                ->get();

            \Illuminate\Support\Facades\Log::info('All messages for staff', [
                'staff_id' => $staffId,
                'total_messages' => $allStaffMessages->count(),
                'ocr_usernames' => $allStaffMessages->pluck('ocr_instagram_username')->unique()->values()->toArray(),
            ]);

            // Try multiple matching strategies
            $matchingMessages = collect();

            \Illuminate\Support\Facades\Log::info('Starting OCR username search with multiple strategies', [
                'extracted_username' => $instagramUsername,
                'base_prefix' => $basePrefix,
                'has_suffix' => $hasSuffix,
                'suffix_prefix' => $suffixPrefix,
            ]);

            // Strategy 1: Exact and partial matches
            $strategy1 = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            })
                ->where(function ($q) use ($instagramUsername) {
                    $q->where('ocr_instagram_username', $instagramUsername)
                        ->orWhere('ocr_instagram_username', 'like', $instagramUsername . '%')
                        ->orWhere(function ($q2) use ($instagramUsername) {
                            $dbDriver = DB::connection()->getDriverName();
                            if ($dbDriver === 'sqlite') {
                                $q2->whereRaw('? LIKE (ocr_instagram_username || \'%\')', [$instagramUsername]);
                            } else {
                                $q2->whereRaw('? LIKE CONCAT(ocr_instagram_username, \'%\')', [$instagramUsername]);
                            }
                        });
                })
                ->with('canvassingCycle.prospect')
                ->orderBy('submitted_at', 'desc')
                ->get();

            \Illuminate\Support\Facades\Log::info('Strategy 1 results', [
                'found' => $strategy1->count(),
            ]);

            if ($strategy1->isNotEmpty()) {
                $matchingMessages = $strategy1;
                \Illuminate\Support\Facades\Log::info('Using Strategy 1 - found matches');
            } else {
                // Strategy 2: Prefix matching (for truncated usernames)
                if ($hasSuffix && strlen($basePrefix) >= 8) {
                    $strategy2 = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                        $q->where('staff_id', $staffId);
                    })
                        ->where('ocr_instagram_username', 'like', $basePrefix . '_%')
                        ->with('canvassingCycle.prospect')
                        ->orderBy('submitted_at', 'desc')
                        ->get();

                    \Illuminate\Support\Facades\Log::info('Strategy 2 results', [
                        'found' => $strategy2->count(),
                        'pattern' => $basePrefix . '_%',
                    ]);

                    if ($strategy2->isNotEmpty()) {
                        $matchingMessages = $strategy2;
                        \Illuminate\Support\Facades\Log::info('Using Strategy 2 - found matches');
                    }
                }

                // Strategy 3: Prefix + suffix prefix matching
                if ($matchingMessages->isEmpty() && $hasSuffix && strlen($basePrefix) >= 8 && strlen($suffixPrefix) >= 3) {
                    $strategy3 = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                        $q->where('staff_id', $staffId);
                    })
                        ->where('ocr_instagram_username', 'like', $basePrefix . '_' . $suffixPrefix . '%')
                        ->with('canvassingCycle.prospect')
                        ->orderBy('submitted_at', 'desc')
                        ->get();

                    \Illuminate\Support\Facades\Log::info('Strategy 3 results', [
                        'found' => $strategy3->count(),
                        'pattern' => $basePrefix . '_' . $suffixPrefix . '%',
                    ]);

                    if ($strategy3->isNotEmpty()) {
                        $matchingMessages = $strategy3;
                        \Illuminate\Support\Facades\Log::info('Using Strategy 3 - found matches');
                    }
                }

                // Strategy 4: Extract prefix from stored OCR username and compare (PHP filter)
                if ($matchingMessages->isEmpty() && strlen($basePrefix) >= 8) {
                    // Get all messages and filter in PHP (more reliable across databases)
                    $allMessages = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                        $q->where('staff_id', $staffId);
                    })
                        ->with('canvassingCycle.prospect')
                        ->get();

                    $strategy4 = $allMessages->filter(function ($msg) use ($basePrefix) {
                        if (!$msg->ocr_instagram_username) {
                            return false;
                        }
                        $storedParts = explode('_', $msg->ocr_instagram_username);
                        return isset($storedParts[0]) && $storedParts[0] === $basePrefix;
                    });

                    \Illuminate\Support\Facades\Log::info('Strategy 4 results', [
                        'found' => $strategy4->count(),
                        'total_messages_checked' => $allMessages->count(),
                        'base_prefix' => $basePrefix,
                    ]);

                    if ($strategy4->isNotEmpty()) {
                        $matchingMessages = $strategy4;
                        \Illuminate\Support\Facades\Log::info('Using Strategy 4 - found matches');
                    }
                }
            }

            // Strategy 5: Levenshtein distance (Fuzzy Match) on stored OCR usernames
            // This handles cases like missing first letter (edai.cekni vs kedai.cekni)
            if ($matchingMessages->isEmpty()) {
                $allMessages = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                    $q->where('staff_id', $staffId);
                })
                    ->with('canvassingCycle.prospect')
                    ->get();

                $bestMatch = null;
                $minDistance = 100;
                $bestIsActive = false;

                foreach ($allMessages as $msg) {
                    if (!$msg->ocr_instagram_username)
                        continue;

                    // Normalization comparison (bonus strategy)
                    // If normalized strings match, consider it distance 0
                    $normInput = preg_replace('/[^a-z0-9]/', '', $instagramUsername);
                    $normStored = preg_replace('/[^a-z0-9]/', '', $msg->ocr_instagram_username);

                    if ($normInput === $normStored && strlen($normInput) > 5) {
                        $dist = 0;
                    } else {
                        $dist = levenshtein($instagramUsername, $msg->ocr_instagram_username);
                    }

                    // Threshold logic
                    $len = strlen($instagramUsername);
                    $threshold = ($len < 8) ? 1 : (($len < 15) ? 2 : 3);

                    if ($dist <= $threshold) {
                        // Valid match candidate. Now check quality.
                        // MUST have a cycle and prospect to be useful
                        if (!$msg->canvassingCycle || !$msg->canvassingCycle->prospect) {
                            continue;
                        }

                        $isActive = in_array($msg->canvassingCycle->status, ['active', 'ongoing', 'sedang berlangsung']);

                        // Selection logic:
                        // 1. If we don't have a match yet, take this one
                        if (!$bestMatch) {
                            $bestMatch = $msg;
                            $minDistance = $dist;
                            $bestIsActive = $isActive;
                            continue;
                        }

                        // 2. If we have a match, try to beat it
                        if ($dist < $minDistance) {
                            // If explicit active preference needed:
                            if ($isActive && !$bestIsActive && ($dist - $minDistance <= 1)) {
                                $bestMatch = $msg;
                                $minDistance = $dist;
                                $bestIsActive = $isActive;
                            } elseif ($dist < $minDistance) {
                                $bestMatch = $msg;
                                $minDistance = $dist;
                                $bestIsActive = $isActive;
                            }
                        } elseif ($dist == $minDistance) {
                            // Same distance? Prefer active.
                            if ($isActive && !$bestIsActive) {
                                $bestMatch = $msg;
                                $bestIsActive = $isActive;
                            }
                        }
                    }
                }

                if ($bestMatch) {
                    $matchingMessages = collect([$bestMatch]);
                    \Illuminate\Support\Facades\Log::info('Found match via Strategy 5 (Levenshtein+ActiveCheck)', [
                        'extracted' => $instagramUsername,
                        'matched' => $bestMatch->ocr_instagram_username,
                        'distance' => $minDistance,
                        'is_active' => $bestIsActive
                    ]);
                }

                \Illuminate\Support\Facades\Log::info('OCR username search results', [
                    'found_messages' => $matchingMessages->count(),
                    'searched_prefix' => $basePrefix,
                    'searched_suffix_prefix' => $suffixPrefix,
                ]);

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
                            'match_method' => 'OCR fallback',
                        ]);
                    }
                }

                // Strategy 6: Levenshtein on Active Prospects directly (Run if prospect still not found)
                if (!$prospect) {
                    $activeProspects = Prospect::whereHas('canvassingCycles', function ($q) use ($staffId) {
                        $q->where('staff_id', $staffId)
                            ->whereIn('status', ['active', 'ongoing', 'sedang berlangsung']);
                    })->get();

                    $bestProspect = null;
                    $minDist = 100;

                    // Debug vars for error message
                    $closestMismatchName = null;
                    $closestMismatchDist = 100;

                    foreach ($activeProspects as $p) {
                        // Sanitize DB value (handle trailing spaces, mixed case)
                        $storedUsername = strtolower(trim($p->instagram_username));

                        // Normalization comparison (bonus strategy)
                        $normInput = preg_replace('/[^a-z0-9]/', '', $instagramUsername);
                        $normStored = preg_replace('/[^a-z0-9]/', '', $storedUsername);

                        if ($normInput === $normStored && strlen($normInput) > 5) {
                            $dist = 0;
                        } else {
                            $dist = levenshtein($instagramUsername, $storedUsername);
                        }

                        $len = strlen($instagramUsername);
                        $threshold = ($len < 8) ? 1 : (($len < 15) ? 2 : 3);

                        // Log debug info
                        if (strpos($storedUsername, 'kedai') !== false || strpos($instagramUsername, 'edai') !== false) {
                            \Illuminate\Support\Facades\Log::info('Strategy 6 Comparison Debug', [
                                'input' => $instagramUsername,
                                'stored_raw' => $p->instagram_username,
                                'stored_clean' => $storedUsername,
                                'dist' => $dist,
                                'threshold' => $threshold,
                                'match' => ($dist <= $threshold)
                            ]);
                        }

                        if ($dist <= $threshold && $dist < $minDist) {
                            $minDist = $dist;
                            $bestProspect = $p;
                        } else {
                            // Track closest mismatch for error reporting
                            if ($dist < $closestMismatchDist) {
                                $closestMismatchDist = $dist;
                                $closestMismatchName = $storedUsername;
                            }
                        }
                    }

                    if ($bestProspect) {
                        $prospect = $bestProspect;
                        \Illuminate\Support\Facades\Log::info('Found prospect via Strategy 6 (Levenshtein on Prospect)', [
                            'extracted' => $instagramUsername,
                            'matched' => $prospect->instagram_username,
                            'distance' => $minDist
                        ]);
                    }
                }

                // Original last resort logic (if still not found)
                if (!$prospect && strlen($basePrefix) >= 8) {
                    $prospect = Prospect::where('instagram_username', 'like', $basePrefix . '_%')
                        ->whereHas('canvassingCycles', function ($q) use ($staffId) {
                            $q->where('staff_id', $staffId)
                                ->whereIn('status', ['active', 'ongoing', 'sedang berlangsung']);
                        })
                        ->first();

                    if ($prospect) {
                        \Illuminate\Support\Facades\Log::info('Found prospect via last resort prefix match', [
                            'prospect_id' => $prospect->id,
                            'stored_username' => $prospect->instagram_username,
                            'searched_prefix' => $basePrefix,
                        ]);
                    }
                }

            }

            // Canvassing and Follow-up logic
            \Illuminate\Support\Facades\Log::info('About to check stage', [
                'stage' => $stage,
                'prospect_found' => $prospect ? 'yes' : 'no',
            ]);

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
                    ->whereIn('status', ['active', 'ongoing', 'sedang berlangsung'])
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
                    // Log all prospects for this staff to help debug
                    $allProspects = Prospect::whereHas('canvassingCycles', function ($q) use ($staffId) {
                        $q->where('staff_id', $staffId)
                            ->whereIn('status', ['active', 'ongoing', 'sedang berlangsung']);
                    })->get(['id', 'instagram_username']);

                    // Get all OCR usernames from messages for this staff
                    $allOcrUsernames = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                        $q->where('staff_id', $staffId);
                    })
                        ->whereNotNull('ocr_instagram_username')
                        ->distinct()
                        ->pluck('ocr_instagram_username')
                        ->toArray();

                    \Illuminate\Support\Facades\Log::error('Prospect not found for follow-up', [
                        'extracted_username' => $instagramUsername,
                        'staff_id' => $staffId,
                        'stage' => $stage,
                        'base_prefix' => $basePrefix ?? 'N/A',
                        'all_prospects_for_staff' => $allProspects->map(function ($p) {
                            return ['id' => $p->id, 'username' => $p->instagram_username];
                        })->toArray(),
                        'all_ocr_usernames_for_staff' => $allOcrUsernames,
                        'total_prospects' => $allProspects->count(),
                        'total_ocr_usernames' => count($allOcrUsernames),
                    ]);

                    $errorMessage = 'Prospect tidak ditemukan (v2). Follow-up harus untuk prospect yang sudah di-canvassing. ';
                    $errorMessage .= 'Username yang dicari: ' . $instagramUsername . '. ';
                    if ($allProspects->isNotEmpty()) {
                        $errorMessage .= 'Prospects yang ada: ' . $allProspects->pluck('instagram_username')->implode(', ') . '. ';
                    }
                    if (!empty($allOcrUsernames)) {
                        $errorMessage .= 'OCR usernames yang tersimpan: ' . implode(', ', array_slice($allOcrUsernames, 0, 5)) . (count($allOcrUsernames) > 5 ? '...' : '') . '.';
                    }

                    return [
                        'valid' => false,
                        'error' => $errorMessage,
                        'cycle' => null,
                    ];
                }

                // If prospect still not found and logic reached here, it MUST be stage 0 (canvassing)
                // because stage > 0 would have returned error above.
                // So we Create a New Prospect and Cycle.
                if (!$prospect) {
                    // Logic check: Only allow creation for stage 0
                    if ($stage > 0) {
                        return [
                            'valid' => false,
                            'error' => "Prospect tidak ditemukan (v3). Follow-up harus untuk prospect yang sudah ada.",
                            'cycle' => null
                        ];
                    }

                    try {
                        \Illuminate\Support\Facades\Log::info('Creating new prospect and cycle for new canvassing', [
                            'username' => $instagramUsername,
                            'staff_id' => $staffId
                        ]);

                        $prospect = Prospect::create([
                            'instagram_username' => $instagramUsername,
                            'category' => 'FnB',
                        ]);

                        $cycle = CanvassingCycle::create([
                            'prospect_id' => $prospect->id,
                            'staff_id' => $staffId,
                            'current_stage' => 0,
                            'status' => 'active',
                            'start_date' => now(),
                            'last_followup_date' => now(),
                        ]);

                        return [
                            'valid' => true,
                            'cycle' => $cycle,
                        ];
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to create new prospect', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        return [
                            'valid' => false,
                            'error' => "Gagal membuat prospect baru: " . $e->getMessage(),
                            'cycle' => null,
                        ];
                    }
                }

                $cycle = CanvassingCycle::where('prospect_id', $prospect->id)
                    ->where('staff_id', $staffId)
                    ->whereIn('status', ['active', 'ongoing', 'sedang berlangsung'])
                    ->first();

                if (!$cycle) {
                    // Debug: check if ANY cycle exists for this prospect (even if not active)
                    $anyCycle = CanvassingCycle::where('prospect_id', $prospect->id)
                        ->where('staff_id', $staffId)
                        ->latest()
                        ->first();

                    if ($anyCycle) {
                        return [
                            'valid' => false,
                            'error' => "Siklus canvassing untuk prospect '{$prospect->instagram_username}' tidak aktif (Status: {$anyCycle->status}). Hubungi supervisor jika status ditolak.",
                            'cycle' => null,
                        ];
                    }

                    return [
                        'valid' => false,
                        'error' => "Tidak ditemukan siklus canvassing aktif untuk prospect '{$prospect->instagram_username}'. Pastikan anda sudah melakukan canvassing awal.",
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

        return [
            'valid' => false,
            'error' => 'Validasi gagal: Kesalahan internal (Logic Fallthrough)',
            'cycle' => null,
        ];
    }

    /**
     * Check daily targets
     */
    public function checkDailyTargets(int $staffId, ?string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $target = 50;

        $canvassingCount = Message::whereHas('canvassingCycle', function ($query) use ($staffId) {
            $query->where('staff_id', $staffId);
        })
            ->where('stage', 0)
            ->whereDate('submitted_at', $date)
            ->count();

        $followUpCount = Message::whereHas('canvassingCycle', function ($query) use ($staffId) {
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

