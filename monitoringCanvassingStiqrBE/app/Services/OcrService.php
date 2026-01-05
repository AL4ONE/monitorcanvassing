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
                'result_preview' => $result ? substr($result, 0, 500) : 'null', // Extended preview
                'result_first_200_chars' => $result ? substr($result, 0, 200) : 'null', // First 200 chars (header area)
                'expected_stage' => $expectedStage,
                'is_followup' => $expectedStage > 0,
            ]);

            if ($result && trim($result) !== '') {
                $parsed = $this->parseOcrResult($result, $expectedStage);
                Log::info('OCR Parsed Result', [
                    'instagram_username' => $parsed['instagram_username'],
                    'message_length' => strlen($parsed['message_snippet'] ?? ''),
                    'date' => $parsed['date'],
                    'expected_stage' => $expectedStage,
                    'is_followup' => $expectedStage > 0,
                    'ocr_preview' => substr($result, 0, 500), // First 500 chars for debugging
                    'ocr_first_200_chars' => substr($result, 0, 200), // First 200 chars (header area)
                    'username_found' => !empty($parsed['instagram_username']),
                ]);

                // If username not found, log more details (for both canvassing and follow-up)
                if (empty($parsed['instagram_username'])) {
                    Log::error('OCR failed to extract username - DETAILED DEBUG INFO', [
                        'expected_stage' => $expectedStage,
                        'is_followup' => $expectedStage > 0,
                        'ocr_text_preview' => substr($result, 0, 1000), // Extended to 1000 chars
                        'ocr_text_length' => strlen($result),
                        'ocr_text_first_200_chars' => substr($result, 0, 200), // First 200 chars (header area)
                        'message_snippet' => substr($parsed['message_snippet'] ?? '', 0, 200),
                        'date_found' => $parsed['date'],
                        'note' => 'Check parseOcrResult logs below for header extraction and pattern matching details',
                    ]);
                }

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
     * @param int|null $expectedStage Expected stage (ONLY used for message filtering, NOT for username extraction)
     * IMPORTANT: Username extraction is IDENTICAL for canvassing and follow-up - no differences!
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

        // Log header area for debugging
        Log::info('Header area extracted', [
            'expected_stage' => $expectedStage,
            'is_followup' => $expectedStage > 0,
            'header_length' => strlen($headerText),
            'header_preview' => substr($headerText, 0, 400),
            'full_text_length' => strlen($normalizedText),
            'note' => 'Username extraction is IDENTICAL for canvassing and follow-up',
        ]);

        // Common words that appear in messages (NOT usernames) - MUST filter these out
        $commonWords = [
            'lihat',
            'profil',
            'tanyakan',
            'instagram',
            'bazar',
            'event',
            'bazaar',
            'kasir',
            'qris',
            'aplikasi',
            'gratis',
            'mdr',
            'umkm',
            'stiqr',
            'bhanu',
            'transaksi',
            'transaks',
            'whatsapp',
            'nomor',
            'nama',
            'usaha',
            'langganan',
            'biaya',
            'operasional',
            'masuk',
            'nanti',
            'tau',
            'nggak',
            'kakak',
            'kak',
            'halo',
            'terima',
            'kasih',
            'kirim',
            'pesan',
            'balas',
            'membalas',
            'coba',
            'demo',
            'info',
            'ready',
            'david',
            'kopi',
            'kedai',
            'bergabung',
            'joined',
            'pengikut',
            'followers',
            'obrolan',
            'bisnis',
            'chat',
            'memulai',
            'dengan',
            'perkenalkan',
            'perkenalan',
            'dari',
            'menawarkan',
            'pembuatan',
            'sudah',
            'include',
            'untuk',
            'kebutuhan',
            'harian',
            'maupun',
            'penggunaan',
            'tanpa',
            'biaya',
            'jadi',
            'cocok',
            'banget',
            'selain',
            'itu',
            'juga',
            'dapet',
            'terkait',
            'yang',
            'cocok',
            'jika',
            'tertarik',
            'jelaskan',
            'detail',
            'fiturnya',
            'lebih',
            'lanjut',
            'terima',
            'kasih',
            'udah',
            'belum',
            'kalau',
            'memasuki',
            'makin',
            'naik',
            'karena',
            'inflasi',
            'bahan',
            'pokok',
            'harus',
            'efisien',
            'supaya',
            'tetap',
            'untung',
            'kami',
            'bisa',
            'bantu',
            'rapiin',
            'simpel',
            'mau',
            'buatkan',
            'akunnya',
            'silakan',
            'membalas',
            'sekarang',
            'banyak',
            'yang',
            'bayar',
            'jutaan',
            'tahun',
            'cuma',
            'buat',
            'pakai',
            'padahal',
            'eranya',
            'ekosistem',
            'digital',
            'ringan',
            'dibuat',
            'khusus',
            'biar',
            'nggak',
            'kebebanan',
            'pembeli',
            'percaya',
            'kalau',
            'dapat',
            'struk',
            'otomatis',
            'bikin',
            'balik',
            'lagi',
            'sudah',
            'support',
            'ribet',
            'mau',
            'lihat',
            'contoh',
            'membalas',
            'siapin',
            'bisa',
            'dipakai',
            'barengan',
            'sama',
            'atau',
            'pos',
            'bandingkan',
            'terlebih',
            'dahulu',
            'aktifkan',
            'hari',
            'ini',
            'merchant',
            'kemarin',
            'ikut',
            'festival',
            'jualannya',
            'exposure',
            'memang',
            'bukan',
            'cuma',
            'transaksi',
            'tapi',
            'buka',
            'peluang',
            'baru',
            'ingin',
            'selanjutnya',
            'makin',
            'pindah',
            'sistem',
            'sadar',
            'lama',
            'sudah',
            'cocok',
            'kondisi',
            'pilihan',
            'fokus',
            'ekosistem',
            'lagi',
            'bergerak',
            'cepat',
            'arah',
            'murah',
            'dibangun',
            'seperti',
            'tetap',
            'berkembang',
            'buatin',
            'langsung',
            'jalan',
            'fiturnya'
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
        // Pattern 1.5: Username BEFORE "Obrolan bisnis" (follow-up screenshot format)
        // This appears in follow-up screenshots: "uptrend.kopi Obrolan bisnis"
        // The username appears in the header but WITHOUT "memulai obrolan dengan" text
        elseif (preg_match('/([a-zA-Z0-9._]{5,30})\s+(?:Obrolan|obrolan)\s+(?:bisnis|business)/i', $headerText, $matches)) {
            $potentialUsername = strtolower(trim($matches[1]));
            // Check it's not a common word and not a number-only string
            if (!in_array($potentialUsername, $commonWords) && !is_numeric($potentialUsername)) {
                $username = $potentialUsername;
                Log::info('Found username via Pattern 1.5 (before Obrolan bisnis)', ['username' => $username]);
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
        if (!$username && preg_match('/@\s*([a-zA-Z0-9._]{5,30})/', $headerText, $matches)) {
            $potentialUsername = strtolower(trim($matches[1]));
            // For both canvassing and follow-up: be lenient - just check it's not a common word
            // This handles usernames without underscore (e.g., "kedaikopidavid")
            if (!in_array($potentialUsername, $commonWords)) {
                $username = $potentialUsername;
                Log::info('Found username via Pattern 3 (@username)', ['username' => $username]);
            }
        }

        // Pattern 3c: Username at the very start or following back arrow (<- or <)
        // Common in dark mode screenshots where header text is "<- @username" or similar
        if (!$username) {
            // Look for username at start of line in header (not mid-sentence)
            // Removed 'k' and '\s' to prevent matching random words in messages
            if (preg_match('/(?:^|[\n\r])(?:[<←])\s*@?\s*([a-zA-Z0-9._]{8,30})/u', $headerText, $matches)) {
                $potentialUsername = strtolower(trim($matches[1]));
                // VALIDATE BEFORE ACCEPTING - must be at least 8 chars and not a common word
                if (!in_array($potentialUsername, $commonWords) && strlen($potentialUsername) >= 8) {
                    $username = $potentialUsername;
                    Log::info('Found username via Pattern 3c (back arrow)', ['username' => $username]);
                } else {
                    Log::info('Pattern 3c matched but failed validation', [
                        'matched' => $potentialUsername,
                        'length' => strlen($potentialUsername),
                        'reason' => strlen($potentialUsername) < 8 ? 'too_short' : 'common_word'
                    ]);
                }
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
            // Updated regex to handle cases like "Kedai Kopi David kedaikopidavid langganan..."
            // Match capitalized name followed by lowercase username (username can be followed by anything)
            if (preg_match('/(?:[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+([a-z0-9_]{8,30})(?=\s|$|[A-Z])/i', $headerTop, $matches)) {
                $potentialUsername = strtolower(trim($matches[1]));
                // Use the same commonWords array defined at the top
                if (!in_array($potentialUsername, $commonWords) && strlen($potentialUsername) >= 8) {
                    // Check position - if very early (first 150 chars), accept regardless of what comes after
                    // This handles cases like "Kedai Kopi David kedaikopidavid langganan..." where
                    // username is in header but immediately followed by message text
                    $matchPos = strpos($headerTop, $matches[0]);
                    if ($matchPos !== false && $matchPos < 150) {
                        $username = $potentialUsername;
                        Log::info('Found username via Pattern 3b (after capitalized name)', [
                            'username' => $username,
                            'match' => $matches[0],
                            'position' => $matchPos,
                            'note' => 'Accepted because appears early in header (first 150 chars)',
                        ]);
                    }
                }
            }
            // Alternative: Look for standalone lowercase username in first 250 chars (very likely to be username)
            // MUST search ONLY in header area
            if (!$username) {
                $headerTop250 = substr($headerText, 0, 250);
                // Try to find any word that looks like a username (8+ chars, alphanumeric + underscore)
                if (preg_match_all('/\b([a-z0-9_]{8,30})\b/i', $headerTop250, $allMatches, PREG_SET_ORDER)) {
                    foreach ($allMatches as $match) {
                        $potentialUsername = strtolower(trim($match[1]));
                        // Use the same commonWords array defined at the top
                        if (!in_array($potentialUsername, $commonWords) && strlen($potentialUsername) >= 8) {
                            // Check if it's not part of a sentence (should be standalone)
                            $pos = stripos($headerTop250, $potentialUsername);
                            if ($pos !== false) {
                                $contextBefore = substr($headerTop250, max(0, $pos - 15), 15);
                                $contextAfter = substr($headerTop250, $pos + strlen($potentialUsername), 15);
                                $context = $contextBefore . ' ' . $contextAfter;

                                // Check if it's NOT near message keywords
                                // Expanded to include FU-7 keywords to prevent "transaks" from being accepted
                                $hasMessageKeywords = preg_match('/(perkenalkan|halo\s+kak|kirim|pesan|balas|terima|kasih|langganan|gratis|aplikasi|qris|kasir|transaksi|transaks|ekosistem|umkm|bergerak|digital|murah|simpel|tetap|berkembang|ready|stiqr|bhanu)/i', $context);

                                // Accept if:
                                // 1. Not near message keywords AND
                                // 2. (Appears early in header OR surrounded by spaces OR near header keywords)
                                // OR
                                // 3. Appears VERY early in header (first 100 chars) - definitely header area, ignore message keywords
                                $isEarly = $pos < 150;
                                $isVeryEarly = $pos < 100; // First 100 chars = definitely header, ignore message keywords
                                $isSurroundedBySpaces = preg_match('/^[\s]*$/', trim($contextBefore) . trim($contextAfter));
                                $hasHeaderKeywords = preg_match('/(obrolan|bisnis|chat|bergabung|joined|profil|profile|memulai|dengan|pengikut|followers)/i', $context);

                                // If very early (first 100 chars), accept regardless of message keywords
                                // This handles cases like "Kedai Kopi David kedaikopidavid langganan..."
                                // where username is in header but followed by message text
                                if ($isVeryEarly) {
                                    $username = $potentialUsername;
                                    Log::info('Found username via Pattern 3b (standalone in header - VERY EARLY)', [
                                        'username' => $username,
                                        'position' => $pos,
                                        'is_very_early' => true,
                                        'context' => substr($context, 0, 40),
                                    ]);
                                    break;
                                }

                                // Otherwise, check normal conditions
                                if (!$hasMessageKeywords && ($isEarly || $isSurroundedBySpaces || $hasHeaderKeywords)) {
                                    $username = $potentialUsername;
                                    Log::info('Found username via Pattern 3b (standalone in header)', [
                                        'username' => $username,
                                        'position' => $pos,
                                        'is_early' => $isEarly,
                                        'is_surrounded' => $isSurroundedBySpaces,
                                        'has_header_keywords' => $hasHeaderKeywords,
                                    ]);
                                    break;
                                }
                            }
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
        // CRITICAL: Only search in first 500 chars of header (very top area) to avoid message area
        // More lenient: accept if it's in header area and not a common word, even without header keywords
        if (!$username) {
            $headerTopOnly = substr($headerText, 0, 500); // Extended to 500 chars for better coverage
            if (preg_match_all('/\b([a-z0-9_]{8,30})\b/i', $headerTopOnly, $allMatches, PREG_SET_ORDER)) {
                foreach ($allMatches as $match) {
                    $potentialUsername = strtolower(trim($match[1]));

                    // For both canvassing and follow-up: be lenient (min 8 chars)
                    // This handles usernames without underscore that were canvassed
                    $isValidLength = strlen($potentialUsername) >= 8;

                    if ($isValidLength) {
                        // Use the same commonWords array defined at the top
                        if (!in_array($potentialUsername, $commonWords)) {
                            // Check position - must be in first 500 chars (header area)
                            $pos = stripos($headerTopOnly, $potentialUsername);
                            if ($pos !== false && $pos < 500) {
                                $contextBefore = substr($headerTopOnly, max(0, $pos - 60), 60);
                                $contextAfter = substr($headerTopOnly, $pos + strlen($potentialUsername), 60);
                                $context = $contextBefore . ' ' . $contextAfter;

                                // Check if it's NOT near message keywords (more important than header keywords)
                                // Expanded to include FU-7 keywords to prevent "transaks" from being accepted
                                $hasMessageKeywords = preg_match('/(perkenalkan|halo\s+kak|kirim\s+pesan|balas|terima\s+kasih|langganan|gratis|aplikasi|qris|kasir|halo\s+kakak|transaksi|transaks|ekosistem|umkm|bergerak|digital|murah|simpel|tetap|berkembang|ready|stiqr|bhanu)/i', $context);

                                // If it's in header area and NOT near message keywords, accept it
                                // Also check if it's near header keywords OR appears early in header (first 300 chars)
                                $hasHeaderKeywords = preg_match('/(obrolan|bisnis|chat|bergabung|joined|profil|profile|memulai|dengan|pengikut|followers)/i', $context);
                                $isEarlyInHeader = $pos < 300; // First 300 chars = very top header

                                // More lenient: accept if not near message keywords and appears in first 300 chars
                                // OR if it's in first 200 chars (very early = definitely header)
                                $isVeryEarly = $pos < 200;

                                if (!$hasMessageKeywords && ($hasHeaderKeywords || $isEarlyInHeader || $isVeryEarly)) {
                                    $username = $potentialUsername;
                                    Log::info('Found username via Pattern 6 (fallback)', [
                                        'username' => $username,
                                        'position' => $pos,
                                        'has_header_keywords' => $hasHeaderKeywords,
                                        'is_early' => $isEarlyInHeader,
                                        'is_very_early' => $isVeryEarly,
                                        'has_message_keywords' => $hasMessageKeywords,
                                        'context' => substr($context, 0, 100),
                                    ]);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Pattern 7: Last resort - any valid username in first 200 chars that's not a common word
        // This is the most aggressive pattern, only used if all others fail
        if (!$username) {
            $headerVeryTop = substr($headerText, 0, 200);
            if (preg_match_all('/\b([a-z0-9_]{8,30})\b/i', $headerVeryTop, $allMatches, PREG_SET_ORDER)) {
                foreach ($allMatches as $match) {
                    $potentialUsername = strtolower(trim($match[1]));

                    if (strlen($potentialUsername) >= 8 && !in_array($potentialUsername, $commonWords)) {
                        $pos = stripos($headerVeryTop, $potentialUsername);
                        if ($pos !== false && $pos < 200) {
                            // Check context - must NOT be near message keywords
                            $contextBefore = substr($headerVeryTop, max(0, $pos - 30), 30);
                            $contextAfter = substr($headerVeryTop, $pos + strlen($potentialUsername), 30);
                            $context = $contextBefore . ' ' . $contextAfter;

                            // Expanded to include FU-7 keywords to prevent "transaks" from being accepted
                            $hasMessageKeywords = preg_match('/(perkenalkan|halo\s+kak|kirim|pesan|balas|terima|kasih|langganan|gratis|aplikasi|qris|kasir|transaksi|transaks|ekosistem|umkm|bergerak|digital|murah|simpel|tetap|berkembang|ready|stiqr|bhanu)/i', $context);

                            // Accept if NOT near message keywords and in first 200 chars
                            if (!$hasMessageKeywords) {
                                $username = $potentialUsername;
                                Log::info('Found username via Pattern 7 (last resort)', [
                                    'username' => $username,
                                    'position' => $pos,
                                    'context' => substr($context, 0, 80),
                                ]);
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Pattern 8: Ultra last resort - find ANY username-like string in first 150 chars
        // This is the most aggressive pattern - accept any valid username format if it's very early in header
        if (!$username) {
            $headerUltraTop = substr($headerText, 0, 150);
            // Look for username patterns: alphanumeric + underscore, 8-30 chars
            if (preg_match_all('/([a-z0-9_]{8,30})/i', $headerUltraTop, $allMatches, PREG_SET_ORDER)) {
                foreach ($allMatches as $match) {
                    $potentialUsername = strtolower(trim($match[1]));

                    // Must be at least 8 chars and not a common word
                    if (strlen($potentialUsername) >= 8 && !in_array($potentialUsername, $commonWords)) {
                        $pos = stripos($headerUltraTop, $potentialUsername);
                        if ($pos !== false && $pos < 150) {
                            // Very early in header (first 150 chars) = definitely header area
                            // Only reject if it's clearly a message keyword
                            $contextBefore = substr($headerUltraTop, max(0, $pos - 20), 20);
                            $contextAfter = substr($headerUltraTop, $pos + strlen($potentialUsername), 20);
                            $context = $contextBefore . ' ' . $contextAfter;

                            // Only reject if it's clearly part of a message sentence
                            // Expanded to include FU-7 keywords to prevent "transaks" from being accepted
                            $isMessageSentence = preg_match('/(perkenalkan|halo\s+kak|kirim\s+pesan|balas|terima\s+kasih|langganan|gratis|aplikasi|qris|kasir|halo\s+kakak|transaksi|transaks|ekosistem|umkm|bergerak|digital|murah|simpel|tetap|berkembang|ready|stiqr|bhanu)/i', $context);

                            // Accept if NOT clearly a message sentence and in first 150 chars
                            if (!$isMessageSentence) {
                                $username = $potentialUsername;
                                Log::info('Found username via Pattern 8 (ultra last resort)', [
                                    'username' => $username,
                                    'position' => $pos,
                                    'context' => substr($context, 0, 60),
                                ]);
                                break;
                            }
                        }
                    }
                }
            }
        }


        if ($username) {
            // Aggressively trim dots and underscores from both ends
            $username = trim($username, '._');
            // Remove trailing dots that indicate truncation (e.g., "grandwis..." -> "grandwis")
            $username = preg_replace('/\.{2,}$/', '', $username);
            // Final trim just in case regexp left something
            $username = trim($username, '._');

            // Final validation: SAME for both canvassing and follow-up
            // Just check minimum length (8 chars) - no difference between canvassing and follow-up
            // This ensures consistency - if username was detected, it should be accepted regardless of stage
            $isValid = strlen($username) >= 8;

            if ($isValid) {
                $result['instagram_username'] = $username;
                Log::info('Username extracted successfully', [
                    'username' => $username,
                    'length' => strlen($username),
                    'has_underscore' => strpos($username, '_') !== false,
                    'expected_stage' => $expectedStage,
                    'is_followup' => $expectedStage > 0,
                    'is_fu7' => $expectedStage === 7,
                    'note' => 'Username extraction is IDENTICAL for all stages including FU-7',
                ]);
            } else {
                Log::warning('Extracted username failed validation', [
                    'extracted' => $username,
                    'has_underscore' => strpos($username, '_') !== false,
                    'length' => strlen($username),
                    'expected_stage' => $expectedStage,
                    'validation_rule' => 'min_8_chars',
                ]);
                $result['instagram_username'] = null;
            }
        }

        // Log OCR result for debugging (only first 500 chars to avoid log spam)
        Log::info('OCR Result', [
            'username_found' => $result['instagram_username'],
            'expected_stage' => $expectedStage,
            'is_followup' => $expectedStage > 0,
            'header_preview' => isset($headerText) ? substr($headerText, 0, 400) : '', // First 400 chars of header
            'ocr_preview' => substr($normalizedText, 0, 500),
            'all_patterns_tried' => !$result['instagram_username'] ? 'No username found after trying all patterns' : 'Username found',
            'note' => 'Username extraction is IDENTICAL for canvassing and follow-up - if canvassing works, follow-up should work too',
        ]);

        // If no username found, log more details for debugging
        if (!$result['instagram_username']) {
            // Try to find any potential username candidates in header
            $potentialUsernames = [];
            if (preg_match_all('/\b([a-z0-9_]{8,30})\b/i', substr($headerText ?? '', 0, 500), $matches)) {
                foreach ($matches[1] as $match) {
                    $potential = strtolower(trim($match));
                    if (strlen($potential) >= 8 && !in_array($potential, $commonWords)) {
                        $potentialUsernames[] = $potential;
                    }
                }
            }

            Log::warning('OCR failed to extract username', [
                'expected_stage' => $expectedStage,
                'is_followup' => $expectedStage > 0,
                'header_length' => strlen($headerText ?? ''),
                'header_first_500_chars' => substr($headerText ?? '', 0, 500),
                'normalized_text_length' => strlen($normalizedText),
                'potential_usernames_found' => array_unique($potentialUsernames),
                'common_words_filtered' => count($commonWords),
                'note' => 'Username extraction is IDENTICAL for canvassing and follow-up - check why pattern matching failed',
            ]);
        }

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
            if (strlen($section) < 20)
                continue;

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

