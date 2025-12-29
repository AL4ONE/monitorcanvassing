<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Services\MessageValidationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $validationService;

    public function __construct(MessageValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Get dashboard stats
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));

        // Log for debugging
        Log::info('Dashboard request', [
            'user_id' => $user->id,
            'role' => $user->role,
            'date' => $date,
        ]);

        if ($user->role === 'supervisor') {
            return $this->supervisorDashboard($date);
        } else {
            return $this->staffDashboard($user->id, $date);
        }
    }

    /**
     * Staff dashboard
     */
    private function staffDashboard(int $staffId, string $date)
    {
        // Get targets per stage
        $targetsPerStage = [];
        $target = 50;

        for ($stage = 0; $stage <= 7; $stage++) {
            $count = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            })
                ->where('stage', $stage)
                ->whereDate('submitted_at', $date)
                ->count();

            // Convert stage to string key for frontend compatibility (Object.entries needs string keys)
            $targetsPerStage[(string) $stage] = [
                'count' => $count,
                'target' => $target,
                'met' => $count >= $target,
            ];
        }

        // Get recent messages
        $recentMessages = Message::with(['canvassingCycle.prospect'])
            ->whereHas('canvassingCycle', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            })
            ->whereDate('submitted_at', $date)
            ->orderBy('submitted_at', 'desc')
            ->limit(10)
            ->get();

        // Get pending messages count
        $pendingCount = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
            $q->where('staff_id', $staffId);
        })
            ->where('validation_status', 'pending')
            ->count();

        return response()->json([
            'targets_per_stage' => $targetsPerStage,
            'recent_messages' => $recentMessages,
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Supervisor dashboard
     */
    private function supervisorDashboard(string $date)
    {
        try {
            // Parse date to ensure correct format
            try {
                $dateObj = \Carbon\Carbon::parse($date);
                $date = $dateObj->format('Y-m-d');
            } catch (\Exception $e) {
                $date = Carbon::today()->format('Y-m-d');
            }

            // Get all staff
            $staffs = User::where('role', 'staff')->get();

            Log::info('Supervisor dashboard', [
                'date' => $date,
                'staff_count' => $staffs->count(),
            ]);

            $staffStats = [];
            foreach ($staffs as $staff) {
                // Get targets per stage
                $targetsPerStage = [];
                $target = 50;

                for ($stage = 0; $stage <= 7; $stage++) {
                    $count = Message::whereHas('canvassingCycle', function ($q) use ($staff) {
                        $q->where('staff_id', $staff->id);
                    })
                        ->where('stage', $stage)
                        ->whereDate('submitted_at', $date)
                        ->count();

                    // Convert stage to string key for frontend compatibility
                    $targetsPerStage[(string) $stage] = [
                        'count' => $count,
                        'target' => $target,
                        'met' => $count >= $target,
                    ];
                }

                // Get red flags
                $redFlags = $this->getRedFlags($staff->id, $date);

                // Calculate total messages for this staff on this date
                $totalMessages = 0;
                foreach ($targetsPerStage as $stageData) {
                    $totalMessages += $stageData['count'] ?? 0;
                }

                Log::info('Staff stats', [
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->name,
                    'date' => $date,
                    'total_messages' => $totalMessages,
                    'targets_per_stage' => $targetsPerStage,
                ]);

                $staffStats[] = [
                    'staff' => [
                        'id' => $staff->id,
                        'name' => $staff->name,
                        'email' => $staff->email,
                    ],
                    'targets_per_stage' => $targetsPerStage,
                    'red_flags' => $redFlags,
                ];
            }

            // Overall stats - don't filter by date for pending_quality_checks (show all pending)
            $totalCanvassing = Message::where('stage', 0)->whereDate('submitted_at', $date)->count();
            $totalFollowUp = Message::where('stage', '>', 0)->whereDate('submitted_at', $date)->count();

            Log::info('Supervisor dashboard response', [
                'date' => $date,
                'staff_stats_count' => count($staffStats),
                'overall_stats' => [
                    'total_staff' => $staffs->count(),
                    'total_canvassing' => $totalCanvassing,
                    'total_follow_up' => $totalFollowUp,
                ],
            ]);

            $overallStats = [
                'total_staff' => $staffs->count(),
                'total_canvassing' => $totalCanvassing,
                'total_follow_up' => $totalFollowUp,
                'pending_quality_checks' => Message::where('validation_status', 'pending')
                    ->count(), // All pending, not filtered by date
            ];

            // Chart Data (Last 7 days)
            $chartData = [];
            $startDate = Carbon::parse($date)->subDays(6);

            for ($i = 0; $i < 7; $i++) {
                $currentDate = $startDate->copy()->addDays($i);
                $dateString = $currentDate->format('Y-m-d');
                $displayDate = $currentDate->format('d M'); // e.g. "12 Dec"

                $dailyTotal = Message::whereDate('submitted_at', $dateString)->count();
                $dailySuccess = \App\Models\CanvassingCycle::where('status', 'success')
                    ->whereDate('updated_at', $dateString) // Assuming updated_at reflects when it became success
                    ->count();

                // If updated_at isn't reliable for success date, we might query messages where stage reached final
                // But for now let's use updated_at of cycle for "success" event

                $chartData[] = [
                    'date' => $displayDate,
                    'total_messages' => $dailyTotal,
                    'success_cycles' => $dailySuccess,
                ];
            }

            return response()->json([
                'staff_stats' => $staffStats,
                'overall_stats' => $overallStats,
                'chart_data' => $chartData,
            ]);
        } catch (\Exception $e) {
            Log::error('Supervisor dashboard error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'date' => $date ?? 'unknown',
            ]);

            return response()->json([
                'error' => 'Server Error',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while fetching dashboard data',
            ], 500);
        }
    }

    /**
     * Get red flags for a staff
     */
    private function getRedFlags(int $staffId, string $date): array
    {
        try {
            $flags = [];

            // Check for duplicate hashes
            $duplicateHashes = Message::whereHas('canvassingCycle', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            })
                ->select('screenshot_hash', DB::raw('count(*) as count'))
                ->groupBy('screenshot_hash')
                ->having('count', '>', 1)
                ->count();

            if ($duplicateHashes > 0) {
                $flags[] = [
                    'type' => 'duplicate_hash',
                    'message' => "Ditemukan {$duplicateHashes} screenshot duplikat",
                    'severity' => 'high',
                ];
            }

            // Check for invalid follow-ups (missing previous stage)
            $invalidFollowUps = Message::with(['canvassingCycle'])
                ->whereHas('canvassingCycle', function ($q) use ($staffId) {
                    $q->where('staff_id', $staffId);
                })
                ->where('stage', '>', 0)
                ->whereDate('submitted_at', $date)
                ->get()
                ->filter(function ($message) {
                    if (!$message->canvassing_cycle_id) {
                        return false; // Skip if no cycle ID
                    }
                    $previousStage = Message::where('canvassing_cycle_id', $message->canvassing_cycle_id)
                        ->where('stage', $message->stage - 1)
                        ->exists();
                    return !$previousStage;
                })
                ->count();

            if ($invalidFollowUps > 0) {
                $flags[] = [
                    'type' => 'invalid_followup',
                    'message' => "Ditemukan {$invalidFollowUps} follow-up yang tidak valid (stage sebelumnya tidak ada)",
                    'severity' => 'high',
                ];
            }

            // Check for OCR username mismatch (use partial matching to handle truncated usernames)
            $mismatchedUsernames = Message::with(['canvassingCycle.prospect'])
                ->whereHas('canvassingCycle', function ($q) use ($staffId) {
                    $q->where('staff_id', $staffId);
                })
                ->whereDate('submitted_at', $date)
                ->get()
                ->filter(function ($message) {
                    // Add null check to prevent errors
                    if (!$message->canvassingCycle || !$message->canvassingCycle->prospect) {
                        return false; // Skip if relationship is missing
                    }

                    $prospectUsername = strtolower(trim($message->canvassingCycle->prospect->instagram_username ?? ''));
                    $ocrUsername = strtolower(trim($message->ocr_instagram_username ?? ''));

                    if (empty($ocrUsername)) {
                        return true; // Missing OCR username is a mismatch
                    }

                    // Use partial matching (same logic as findOrCreateCycle)
                    // Check if they match exactly or one is a prefix of the other
                    if ($ocrUsername === $prospectUsername) {
                        return false; // Match
                    }

                    // Check if one starts with the other (handle truncation)
                    $minLength = min(strlen($ocrUsername), strlen($prospectUsername));
                    if ($minLength >= 10) {
                        // If both are at least 10 chars, check if they start the same
                        $ocrPrefix = substr($ocrUsername, 0, $minLength);
                        $prospectPrefix = substr($prospectUsername, 0, $minLength);
                        if ($ocrPrefix === $prospectPrefix) {
                            return false; // Match (one is truncated version of the other)
                        }
                    }

                    // Check prefix matching (for usernames with underscore)
                    $ocrParts = explode('_', $ocrUsername);
                    $prospectParts = explode('_', $prospectUsername);
                    if (count($ocrParts) > 1 && count($prospectParts) > 1) {
                        if ($ocrParts[0] === $prospectParts[0]) {
                            return false; // Match (same prefix, different suffix length)
                        }
                    }

                    return true; // Mismatch
                })
                ->count();

            if ($mismatchedUsernames > 0) {
                $flags[] = [
                    'type' => 'username_mismatch',
                    'message' => "Ditemukan {$mismatchedUsernames} screenshot dengan username tidak sesuai",
                    'severity' => 'medium',
                ];
            }

            return $flags;
        } catch (\Exception $e) {
            Log::error('getRedFlags error', [
                'error' => $e->getMessage(),
                'staff_id' => $staffId,
                'date' => $date,
                'trace' => $e->getTraceAsString(),
            ]);
            // Return empty array on error to prevent dashboard from failing
            return [];
        }
    }
}

