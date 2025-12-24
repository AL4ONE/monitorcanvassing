<?php

namespace App\Services;

class MessageTemplateService
{
    /**
     * Message templates for each day/stage
     */
    private const MESSAGE_TEMPLATES = [
        0 => [ // Canvassing (Day 0)
            'keywords' => ['perkenalkan', 'bhanu', 'stiqr', 'qris', 'kasir', 'aplikasi', 'gratis', 'mdr', '0%', 'umkm'],
            'phrases' => ['perkenalkan aku bhanu', 'qris yang sudah include', 'aplikasi kasirnya', 'gratis tanpa biaya langganan'],
        ],
        1 => [ // Day 1
            'keywords' => ['day 1', '*day 1*', '2026', 'biaya operasional', 'f&b', 'inflasi', 'bahan pokok', 'efisien', 'rapiin transaksi', 'masuk 2026', 'operasional f&b'],
            'phrases' => [
                'masuk 2026 nanti',
                'biaya operasional f&b',
                'biaya operasional f&b makin naik',
                'rapiin transaksi tanpa biaya langganan',
                'kirim nama usaha + nomor whatsapp',
                'tau nggak',
                'masuk 2026 nanti, biaya operasional',
            ],
        ],
        2 => [ // Day 2
            'keywords' => ['day 2', '*day 2*', 'jutaan per tahun', 'kasir dan qris', 'ekosistem digital', 'lebih ringan', 'kebebanan biaya'],
            'phrases' => ['bayar jutaan per tahun', '2026 itu eranya ekosistem digital', 'dibuat khusus untuk umkm', 'kirim nama usaha + nomor whatsapp'],
        ],
        3 => [ // Day 3
            'keywords' => ['day 3', '*day 3*', 'struk wa', 'otomatis', 'pembeli balik lagi', 'support itu tanpa ribet', 'demo'],
            'phrases' => ['struk wa otomatis', 'pembeli balik lagi', 'support itu tanpa ribet', 'balas "demo"'],
        ],
        4 => [ // Day 4
            'keywords' => ['day 4', '*day 4*', 'siapin akun', 'barengan sama qris', 'bandingkan terlebih dahulu', 'coba'],
            'phrases' => ['siapin akun stiqr', 'barengan sama qris atau pos', 'bandingkan terlebih dahulu', 'balas "coba"'],
        ],
        5 => [ // Day 5
            'keywords' => ['day 5', '*day 5*', 'jali-jali festival', 'event', 'exposure event', 'peluang baru', 'info event'],
            'phrases' => ['jali-jali festival', 'exposure event', 'buka peluang baru', 'balas "info event"'],
        ],
        6 => [ // Day 6
            'keywords' => ['day 6', '*day 6*', 'pindah ke sistem', 'pos lama', '2025–2026', 'fokus bantu umkm'],
            'phrases' => ['pindah ke sistem yang lebih efisien', 'biaya pos lama', '2025–2026', 'fokus bantu umkm'],
        ],
        7 => [ // Day 7
            'keywords' => ['day 7', '*day 7*', 'ekosistem umkm', 'bergerak cepat', 'digital yang lebih murah', 'tetap berkembang', 'ready'],
            'phrases' => ['ekosistem umkm lagi bergerak cepat', 'digital yang lebih murah dan simpel', 'tetap berkembang', 'balas "ready"'],
        ],
    ];

    /**
     * Detect stage/day based on message content
     */
    public function detectStageFromMessage(string $messageText): ?int
    {
        $messageText = strtolower($messageText);
        $messageText = preg_replace('/\s+/', ' ', $messageText); // Normalize whitespace
        
        $bestMatch = null;
        $bestScore = 0;

        foreach (self::MESSAGE_TEMPLATES as $stage => $template) {
            $score = 0;
            
            // Check keywords
            foreach ($template['keywords'] as $keyword) {
                if (stripos($messageText, strtolower($keyword)) !== false) {
                    $score += 2; // Keywords are worth 2 points
                }
            }
            
            // Check phrases (more specific, worth more)
            foreach ($template['phrases'] as $phrase) {
                if (stripos($messageText, strtolower($phrase)) !== false) {
                    $score += 5; // Phrases are worth 5 points
                }
            }
            
            // Special check for day markers
            if (preg_match('/\*?\s*day\s*' . $stage . '\s*\*?/i', $messageText)) {
                $score += 10; // Day marker is very strong indicator
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $stage;
            }
        }

        // Only return if score is high enough (at least 5 points)
        return $bestScore >= 5 ? $bestMatch : null;
    }

    /**
     * Validate if message matches expected stage template
     * Only checks if the expected stage template is present in the message
     * It's OK if other stage templates are also present
     */
    public function validateMessageForStage(string $messageText, int $expectedStage): array
    {
        $messageText = strtolower($messageText);
        $messageText = preg_replace('/\s+/', ' ', $messageText);
        
        // Get template for expected stage
        $template = $this->getTemplateForStage($expectedStage);
        
        if (!$template) {
            return [
                'valid' => false,
                'expected_stage' => $expectedStage,
                'detected_stage' => null,
                'match_score' => 'none',
            ];
        }
        
        $score = 0;
        $foundKeywords = [];
        $foundPhrases = [];
        
        // Check keywords
        foreach ($template['keywords'] as $keyword) {
            if (stripos($messageText, strtolower($keyword)) !== false) {
                $score += 2;
                $foundKeywords[] = $keyword;
            }
        }
        
        // Check phrases (more specific, worth more)
        foreach ($template['phrases'] as $phrase) {
            if (stripos($messageText, strtolower($phrase)) !== false) {
                $score += 5;
                $foundPhrases[] = $phrase;
            }
        }
        
        // Special check for day markers
        if (preg_match('/\*?\s*day\s*' . $expectedStage . '\s*\*?/i', $messageText)) {
            $score += 10;
        }
        
        // Valid if score is high enough 
        // Lower threshold to 3 points (at least one keyword match) since messages may contain multiple days
        // This allows validation to pass if Day 1 template is found, even if Day 0 is also present
        $isValid = $score >= 3;
        
        // Also detect what stage was detected (for info)
        $detectedStage = $this->detectStageFromMessage($messageText);
        
        return [
            'valid' => $isValid,
            'expected_stage' => $expectedStage,
            'detected_stage' => $detectedStage,
            'match_score' => $score,
            'found_keywords' => $foundKeywords,
            'found_phrases' => $foundPhrases,
        ];
    }

    /**
     * Get expected message template for a stage
     */
    public function getTemplateForStage(int $stage): ?array
    {
        return self::MESSAGE_TEMPLATES[$stage] ?? null;
    }
}

