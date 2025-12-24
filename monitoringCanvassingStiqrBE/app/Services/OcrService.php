<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    /**
     * Extract data from screenshot using OCR
     *
     * @param string $imagePath Path to the image file
     * @param int|null $expectedStage Expected stage (0-7) to filter messages. If provided, only extract message for that stage.
     * @return array ['instagram_username' => string|null, 'message_snippet' => string|null, 'date' => string|null]
     */
    public function extractData(string $imagePath, ?int $expectedStage = null): array
    {
        try {
            // Try OCR.space API first (free tier available)
            $result = $this->extractWithOcrSpace($imagePath);

            Log::info('OCR API Response', [
                'has_result' => !empty($result),
                'result_length' => $result ? strlen($result) : 0,
                'result_preview' => $result ? substr($result, 0, 200) : 'null',
            ]);

            if ($result && trim($result) !== '') {
                $parsed = $this->parseOcrResult($result, $expectedStage);
                Log::info('OCR Parsed Result', [
                    'instagram_username' => $parsed['instagram_username'],
                    'message_length' => strlen($parsed['message_snippet'] ?? ''),
                    'date' => $parsed['date'],
                    'expected_stage' => $expectedStage,
                    'ocr_preview' => substr($result, 0, 500), // First 500 chars for debugging
                ]);
                return $parsed;
            }

            // Fallback: return empty result
            Log::warning('OCR returned empty result');
            return [
                'instagram_username' => null,
                'message_snippet' => null,
                'date' => null,
            ];
        } catch (\Exception $e) {
            Log::error('OCR extraction failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'instagram_username' => null,
                'message_snippet' => null,
                'date' => null,
            ];
        }
    }

    /**
     * Extract using OCR.space API
     */
    private function extractWithOcrSpace(string $imagePath): ?string
    {
        $apiKey = config('services.ocr_space.api_key');

        Log::info('OCR API Key Check', [
            'has_key' => !empty($apiKey),
            'key_preview' => $apiKey ? substr($apiKey, 0, 5) . '...' : 'null',
        ]);

        if (!$apiKey) {
            Log::warning('OCR API key not found in config');
            return null;
        }

        if (!file_exists($imagePath)) {
            Log::error('Image file not found', ['path' => $imagePath]);
            return null;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::timeout(30)
                ->asMultipart()
                ->attach('file', file_get_contents($imagePath), basename($imagePath))
                ->post('https://api.ocr.space/parse/image', [
                    'apikey' => $apiKey,
                    'language' => 'eng', // English (works well for mixed Indonesian/English text)
                    'OCREngine' => 2, // Use OCR Engine 2 for better accuracy
                ]);

            $status = $response->status();
            Log::info('OCR API Response Status', ['status' => $status]);

            if ($status === 200) {
                $data = $response->json();
                Log::info('OCR API Response Data', [
                    'has_parsed_results' => isset($data['ParsedResults']),
                    'results_count' => isset($data['ParsedResults']) ? count($data['ParsedResults']) : 0,
                    'error_message' => $data['ErrorMessage'] ?? null,
                ]);

                if (isset($data['ParsedResults'][0]['ParsedText'])) {
                    $text = $data['ParsedResults'][0]['ParsedText'];
                    Log::info('OCR Text Extracted', ['length' => strlen($text), 'preview' => substr($text, 0, 300)]);
                    return $text;
                } else {
                    Log::warning('OCR API returned no ParsedText', ['data' => $data]);
                }
            } else {
                $errorData = $response->json();
                Log::error('OCR API Error', [
                    'status' => $status,
                    'response' => $errorData,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('OCR.space API error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return null;
    }

    /**
     * Parse OCR result to extract Instagram username, message, and date
     * @param string $ocrText Raw OCR text
     * @param int|null $expectedStage Expected stage to filter messages (0 = canvassing, more lenient)
     */
    private function parseOcrResult(string $ocrText, ?int $expectedStage = null): array
    {
        $result = [
            'instagram_username' => null,
            'message_snippet' => null,
            'date' => null,
        ];

        // Normalize text - replace common OCR mistakes
        $normalizedText = $ocrText;
        $normalizedText = preg_replace('/\s+/', ' ', $normalizedText); // Normalize whitespace
        $normalizedText = str_replace(["\n", "\r"], ' ', $normalizedText); // Replace newlines with spaces

        // CRITICAL: Extract header area FIRST (first 1000 chars) - this is where username ALWAYS appears
        // NEVER search in message area - messages contain words like "langganan", "gratis", etc. that are NOT usernames
        $headerText = substr($normalizedText, 0, 1000); // Header area = first 1000 chars

        // Common words that appear in messages (NOT usernames) - MUST filter these out
        $commonWords = [
            'lihat', 'profil', 'tanyakan', 'instagram', 'bazar', 'event', 'bazaar', 'kasir', 'qris',
            'aplikasi', 'gratis', 'mdr', 'umkm', 'stiqr', 'bhanu', 'transaksi', 'whatsapp', 'nomor',
            'nama', 'usaha', 'langganan', 'biaya', 'operasional', 'masuk', 'nanti', 'tau', 'nggak',
            'kakak', 'kak', 'halo', 'terima', 'kasih', 'kirim', 'pesan', 'balas', 'membalas', 'coba',
            'demo', 'info', 'ready', 'david', 'kopi', 'kedai', 'bergabung', 'joined', 'pengikut',
            'followers', 'obrolan', 'bisnis', 'chat', 'memulai', 'dengan'
        ];

        // Extract Instagram username with multiple patterns (ordered by priority)
        // IMPORTANT: Always prioritize username from header/top area, not from middle/message area
        // This ensures consistency between canvassing and follow-up screenshots
        $username = null;

        // Pattern 1: "memulai obrolan dengan username" (highest priority - from header/top)
        // This pattern should match: "Anda memulai obrolan dengan bebekcaberawit_grandwisata"
        // MUST search ONLY in header area
        if (preg_match('/memulai\s+obrolan\s+dengan\s+([a-zA-Z0-9._]{5,30})/i', $headerText, $matches)) {
            $potentialUsername = strtolower(trim($matches[1]));
            // For both canvassing and follow-up: be lenient - just check it's not a common word
            // This handles usernames without underscore (e.g., "kedaikopidavid")
            if (!in_array($potentialUsername, $commonWords)) {
                $username = $potentialUsername;
                Log::info('Found username via Pattern 1 (memulai obrolan)', ['username' => $username]);
            }
        }
        // Pattern 2: "obrolan dengan username" or "chat with username" (from header/top)
        // This appears in the header area: "obrolan bisnis" or "obrolan dengan username"
        // MUST search ONLY in header area
        elseif (preg_match('/(?:obrolan|chat)\s+(?:dengan|with|bisnis|business)\s+([a-zA-Z0-9._]{5,30})/i', $headerText, $matches)) {
            $potentialUsername = strtolower(trim($matches[1]));
            // For both canvassing and follow-up: be lenient - just check it's not a common word
            // This handles usernames without underscore (e.g., "kedaikopidavid")
            if (!in_array($potentialUsername, $commonWords)) {
                $username = $potentialUsername;
                Log::info('Found username via Pattern 2 (obrolan dengan)', ['username' => $username]);
            }
        }
        // Pattern 3: @username format (usually in header) - MUST be in header area only
        // Header area already extracted above (first 1000 chars)
        if (!$username && preg_match('/@([a-zA-Z0-9._]{5,30})/', $headerText, $matches)) {
            $potentialUsername = strtolower(trim($matches[1]));
            // For both canvassing and follow-up: be lenient - just check it's not a common word
            // This handles usernames without underscore (e.g., "kedaikopidavid")
            if (!in_array($potentialUsername, $commonWords)) {
                $username = $potentialUsername;
                Log::info('Found username via Pattern 3 (@username)', ['username' => $username]);
            }
        }

        // Pattern 3b: Username in lowercase after capitalized name (e.g., "Kedai Kopi David" followed by "kedaikopidavid")
        // This pattern matches usernames that appear directly below the contact name in header
        if (!$username) {
            // Look for pattern: capitalized words followed by lowercase username (common in Instagram chat header)
            // e.g., "Kedai Kopi David" followed by "kedaikopidavid"
            // Match: [Capitalized Name] followed by [lowercase username] in first 300 chars
            $headerTop = substr($headerText, 0, 300); // First 300 chars = header area (from headerText, not normalizedText)
            // More flexible pattern: match any sequence of capitalized words, then lowercase username
            // Also handle cases where username might be on next line (separated by space/newline)
            if (preg_match('/(?:[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+([a-z0-9_]{8,30})(?:\s|$)/i', $headerTop, $matches)) {
                $potentialUsername = strtolower(trim($matches[1]));
                // Use the same commonWords array defined at the top
                if (!in_array($potentialUsername, $commonWords) && strlen($potentialUsername) >= 8) {
                    $username = $potentialUsername;
                    Log::info('Found username via Pattern 3b (after capitalized name)', [
                        'username' => $username,
                        'match' => $matches[0],
                        'full_match' => $matches[0],
                    ]);
                }
            }
            // Alternative: Look for standalone lowercase username in first 200 chars (very likely to be username)
            // MUST search ONLY in header area
            if (!$username && preg_match('/\b([a-z0-9_]{8,30})\b/', substr($headerText, 0, 200), $matches)) {
                $potentialUsername = strtolower(trim($matches[1]));
                // Use the same commonWords array defined at the top
                if (!in_array($potentialUsername, $commonWords) && strlen($potentialUsername) >= 8) {
                    // Check if it's not part of a sentence (should be standalone)
                    // MUST search ONLY in header area
                    $pos = stripos(substr($headerText, 0, 200), $potentialUsername);
                    if ($pos !== false) {
                        $contextBefore = substr($headerText, max(0, $pos - 10), 10);
                        $contextAfter = substr($headerText, $pos + strlen($potentialUsername), 10);
                        // If surrounded by spaces or at start/end, likely a username
                        if (preg_match('/^[\s]*$/', $contextBefore . $contextAfter) || $pos < 50) {
                            $username = $potentialUsername;
                            Log::info('Found username via Pattern 3b (standalone in header)', [
                                'username' => $username,
                                'position' => $pos,
                            ]);
                        }
                    }
                }
            }
        }

        // Pattern 4: Username in header area ONLY (first 1000 chars = header/top area)
        // Header area already extracted above (first 1000 chars)
        // CRITICAL: Always get username from header, never from middle/profile section
        // This ensures consistency - same username format between canvassing and follow-up
        if (!$username) {
            // Look for username before "pengikut" in header area (not middle)
            if (preg_match('/([a-z0-9_]{8,30})\s*(?:pengikut|followers)/i', $headerText, $matches)) {
                $potentialUsername = strtolower(trim($matches[1]));
                // Use the same commonWords array defined at the top
                // For both canvassing and follow-up: be lenient - just check it's not a common word and min 8 chars
                // This handles usernames without underscore (e.g., "kedaikopidavid")
                if (!in_array($potentialUsername, $commonWords) && strlen($potentialUsername) >= 8) {
                    $username = $potentialUsername;
                    Log::info('Found username via Pattern 4 (pengikut)', ['username' => $username]);
                }
            }
        }

        // Pattern 5: Username before "Bergabung" or join date (common in Instagram profile header)
        // e.g., "kedaikopidavid • Bergabung Mei 2014" or "username Bergabung"
        if (!$username) {
            // Try multiple patterns for "Bergabung" format
            $patterns = [
                '/([a-z0-9_]{8,30})\s*[•·]\s*(?:Bergabung|Joined)/i',  // "username • Bergabung"
                '/([a-z0-9_]{8,30})\s+(?:Bergabung|Joined)/i',         // "username Bergabung"
                '/([a-z0-9_]{8,30})\s*[•·]\s*(?:pengikut|followers)/i', // "username • pengikut"
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $headerText, $matches)) {
                    $potentialUsername = strtolower(trim($matches[1]));
                    // Use the same commonWords array defined at the top
                    if (!in_array($potentialUsername, $commonWords)) {
                        // For canvassing (stage 0), be more lenient - just check minimum length
                        // For follow-ups: also be lenient (min 8 chars) to handle usernames without underscore
                        if (strlen($potentialUsername) >= 8) {
                            $username = $potentialUsername;
                            Log::info('Found username via Pattern 5 (Bergabung)', [
                                'username' => $username,
                                'pattern' => $pattern,
                                'expected_stage' => $expectedStage,
                            ]);
                            break;
                        }
                    }
                }
            }
        }

        // Pattern 6: Fallback - username in header area near "obrolan" or "bisnis" or profile keywords
        if (!$username) {
            if (preg_match_all('/\b([a-z0-9_]{8,30})\b/i', $headerText, $allMatches, PREG_SET_ORDER)) {
                foreach ($allMatches as $match) {
                    $potentialUsername = strtolower(trim($match[1]));

                    // For both canvassing and follow-up: be lenient (min 8 chars)
                    // This handles usernames without underscore that were canvassed
                    $isValidLength = strlen($potentialUsername) >= 8;

                    if ($isValidLength) {
                        // Use the same commonWords array defined at the top
                        if (!in_array($potentialUsername, $commonWords)) {
                            // Check if it appears near header keywords (obrolan, bisnis, bergabung, profil)
                            $pos = stripos($headerText, $potentialUsername);
                            if ($pos !== false) {
                                $contextBefore = substr($headerText, max(0, $pos - 40), 40);
                                $contextAfter = substr($headerText, $pos + strlen($potentialUsername), 40);
                                // If it appears near "obrolan", "bisnis", "bergabung", "profil" in header area
                                if (preg_match('/(obrolan|bisnis|chat|bergabung|joined|profil|profile)/i', $contextBefore . ' ' . $contextAfter)) {
                                    $username = $potentialUsername;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Clean username (remove trailing dots/underscores that might be OCR errors)
        // Also handle truncated usernames from header (e.g., "bebekcaberawit_grandwis..." -> "bebekcaberawit_grandwis")
        if ($username) {
            $username = rtrim($username, '._');
            // Remove trailing dots that indicate truncation (e.g., "grandwis..." -> "grandwis")
            $username = preg_replace('/\.{2,}$/', '', $username);
            $username = rtrim($username, '.');

            // Final validation:
            // - For canvassing (stage 0): be lenient, just check it's not too short (min 8 chars)
            // - For follow-ups: be more flexible - check length (min 8 chars) OR has underscore with min 10 chars
            //   This handles cases where canvassing username doesn't have underscore (e.g., "kedaikopidavid")
            $isValid = false;
            if ($expectedStage === 0) {
                // Canvassing: more lenient - just check minimum length
                $isValid = strlen($username) >= 8;
            } else {
                // Follow-up: more flexible - accept if:
                // 1. Has underscore and min 10 chars (strict), OR
                // 2. Min 8 chars (lenient, for usernames without underscore that were canvassed)
                $isValid = (strpos($username, '_') !== false && strlen($username) >= 10) ||
                          (strlen($username) >= 8);
            }

            if ($isValid) {
                $result['instagram_username'] = $username;
                Log::info('Username extracted successfully', [
                    'username' => $username,
                    'length' => strlen($username),
                    'has_underscore' => strpos($username, '_') !== false,
                    'expected_stage' => $expectedStage,
                ]);
            } else {
                Log::warning('Extracted username failed validation', [
                    'extracted' => $username,
                    'has_underscore' => strpos($username, '_') !== false,
                    'length' => strlen($username),
                    'expected_stage' => $expectedStage,
                    'validation_rule' => $expectedStage === 0 ? 'min_8_chars' : 'min_8_chars_or_underscore_with_min_10_chars',
                ]);
                $result['instagram_username'] = null;
            }
        }

        // Log OCR result for debugging (only first 500 chars to avoid log spam)
        Log::info('OCR Result', [
            'username_found' => $result['instagram_username'],
            'expected_stage' => $expectedStage,
            'header_preview' => isset($headerText) ? substr($headerText, 0, 300) : '', // First 300 chars of header
            'ocr_preview' => substr($normalizedText, 0, 500),
        ]);

        // Extract date (Indonesian format: DD/MM/YYYY or DD-MM-YYYY, or "Hari ini HH.MM")
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $normalizedText, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = strlen($matches[3]) == 2 ? '20' . $matches[3] : $matches[3];
            $result['date'] = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        } elseif (preg_match('/hari\s+ini|today/i', $normalizedText)) {
            // If "Hari ini" or "Today" is found, use today's date
            $result['date'] = date('Y-m-d');
        }

        // Extract ALL messages from screenshot (not just the first one)
        // This is important because follow-up screenshots may contain multiple messages
        $allMessages = [];

        // Split by common message separators (timestamps, "Hari ini", etc.)
        $messageSections = preg_split('/(hari\s+ini\s+[\d:\.]+|today\s+[\d:\.]+)/i', $ocrText);

        foreach ($messageSections as $section) {
            $section = trim($section);
            if (strlen($section) < 20) continue;

            // Look for message content (usually starts with "Halo" or contains STIQR/QRIS)
            if (preg_match('/(halo|selamat|terima\s+kasih|qris|stiqr|day\s+\d+|masuk\s+2026|biaya\s+operasional)/i', $section)) {
                // Clean up - remove common UI elements
                $cleanSection = preg_replace('/(lihat|profil|tanyakan|obrolan|bisnis|pengikut|followers|postingan|posts)/i', '', $section);
                $cleanSection = preg_replace('/\s+/', ' ', $cleanSection);
                $cleanSection = trim($cleanSection);

                if (strlen($cleanSection) > 30) {
                    $allMessages[] = $cleanSection;
                }
            }
        }

        // If no sections found, try extracting all lines that look like messages
        if (empty($allMessages)) {
            $lines = explode("\n", $ocrText);
            $messageLines = [];
            $foundMessageStart = false;

            foreach ($lines as $line) {
                $line = trim($line);
                // Skip profile info
                if (preg_match('/^(pengikut|followers|following|postingan|posts|@|lihat|profil|tanyakan|obrolan|bisnis)/i', $line)) {
                    continue;
                }
                // Look for message indicators
                if (preg_match('/(halo|selamat|terima\s+kasih|qris|stiqr|day\s+\d+|masuk\s+2026|biaya\s+operasional)/i', $line)) {
                    $foundMessageStart = true;
                }
                if ($foundMessageStart && strlen($line) > 10) {
                    $messageLines[] = $line;
                }
            }

            if (!empty($messageLines)) {
                // Group lines into messages (split by empty lines or timestamps)
                $currentMessage = [];
                foreach ($messageLines as $line) {
                    if (preg_match('/^(hari\s+ini|today|\d{1,2}:\d{2})/i', $line)) {
                        if (!empty($currentMessage)) {
                            $allMessages[] = implode(' ', $currentMessage);
                            $currentMessage = [];
                        }
                    } else {
                        $currentMessage[] = $line;
                    }
                }
                if (!empty($currentMessage)) {
                    $allMessages[] = implode(' ', $currentMessage);
                }
            }
        }

        // Filter messages based on expected stage
        if (!empty($allMessages) && $expectedStage !== null && $expectedStage > 0) {
            // For follow-ups, only extract message that matches the expected stage
            // Use MessageTemplateService to detect which message belongs to which stage
            $templateService = app(\App\Services\MessageTemplateService::class);
            $filteredMessages = [];

            foreach ($allMessages as $messageText) {
                $detectedStage = $templateService->detectStageFromMessage($messageText);
                if ($detectedStage === $expectedStage) {
                    $filteredMessages[] = $messageText;
                }
            }

            // If we found a message matching the expected stage, use only that
            // Otherwise, use the last message (most recent, likely the follow-up)
            if (!empty($filteredMessages)) {
                $result['message_snippet'] = substr($filteredMessages[0], 0, 1000);
            } else {
                // Fallback: use last message if no match found
                $result['message_snippet'] = substr(end($allMessages), 0, 1000);
            }
        } elseif (!empty($allMessages)) {
            // For canvassing (stage 0) or no stage specified, include all messages
            $combinedMessages = implode(' ', $allMessages);
            $result['message_snippet'] = substr($combinedMessages, 0, 1000);
        }

        return $result;
    }
}

