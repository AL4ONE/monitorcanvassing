<?php

namespace App\Services;

use App\Models\Prospect;
use App\Models\CanvassingCycle;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Carbon\Carbon;

class ImportService
{
    protected $errors = [];
    protected $imported = 0;
    protected $failed = 0;
    protected $staffId;

    public function __construct($staffId)
    {
        $this->staffId = $staffId;
    }

    /**
     * Import data from Excel/CSV file
     */
    public function importFromSpreadsheet($filePath)
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Extract all images from the spreadsheet
            $images = $this->extractImages($worksheet);

            // Get highest row number
            $highestRow = $worksheet->getHighestRow();

            // Start from row 2 (assuming row 1 is header)
            for ($row = 2; $row <= $highestRow; $row++) {
                try {
                    $this->processRow($worksheet, $row, $images);
                    $this->imported++;
                } catch (\Exception $e) {
                    $this->failed++;
                    $merchantName = $worksheet->getCell("A{$row}")->getValue() ?? "Row {$row}";
                    $this->errors[] = [
                        'row' => $row,
                        'merchant' => $merchantName,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return [
                'total_rows' => $highestRow - 1,
                'imported' => $this->imported,
                'failed' => $this->failed,
                'images_found' => count($images),
                'errors' => $this->errors
            ];

        } catch (\Exception $e) {
            throw new \Exception("Failed to read spreadsheet: " . $e->getMessage());
        }
    }

    /**
     * Process a single row from the spreadsheet
     */
    protected function processRow($worksheet, $rowNumber, $images)
    {
        DB::beginTransaction();

        try {
            // Extract data from columns
            $instagramUsername = $this->cleanValue($worksheet->getCell("A{$rowNumber}")->getValue());
            $category = $this->cleanValue($worksheet->getCell("B{$rowNumber}")->getValue());
            $businessType = $this->cleanValue($worksheet->getCell("C{$rowNumber}")->getValue());
            $channel = $this->cleanValue($worksheet->getCell("D{$rowNumber}")->getValue());
            $instagramLink = $this->cleanValue($worksheet->getCell("E{$rowNumber}")->getValue());
            $status = $this->cleanValue($worksheet->getCell("F{$rowNumber}")->getValue());
            $notes = $this->cleanValue($worksheet->getCell("G{$rowNumber}")->getValue());

            // Validate required fields
            if (empty($instagramUsername)) {
                throw new \Exception("Instagram username is required");
            }

            // Create or get prospect
            $prospect = Prospect::firstOrCreate(
                ['instagram_username' => $instagramUsername],
                [
                    'category' => $this->mapCategory($category),
                    'business_type' => $businessType,
                    'channel' => $this->mapChannel($channel),
                    'instagram_link' => $instagramLink,
                ]
            );

            // Create canvassing cycle
            $cycle = CanvassingCycle::create([
                'prospect_id' => $prospect->id,
                'staff_id' => $this->staffId,
                'start_date' => now(),
                'current_stage' => 0,
                'status' => $this->mapStatus($status),
            ]);

            // Process screenshots from columns H (SS Chat/fu0) and J-P (fu1-fu7)
            $screenshotColumns = [
                0 => 'H',  // SS Chat (canvassing/fu0)
                1 => 'J',  // fu1
                2 => 'K',  // fu2
                3 => 'L',  // fu3
                4 => 'M',  // fu4
                5 => 'N',  // fu5
                6 => 'O',  // fu6
                7 => 'P',  // fu7
            ];

            foreach ($screenshotColumns as $stage => $column) {
                $cellCoordinate = "{$column}{$rowNumber}";

                // Find image for this cell
                $imageData = $this->findImageForCell($images, $cellCoordinate);

                if ($imageData) {
                    // Save image and create message record
                    $screenshotPath = $this->saveImage($imageData, $instagramUsername, $stage);

                    Message::create([
                        'canvassing_cycle_id' => $cycle->id,
                        'stage' => $stage,
                        'category' => $this->mapCategory($category),
                        'screenshot_path' => $screenshotPath,
                        'screenshot_hash' => hash_file('sha256', storage_path('app/public/' . $screenshotPath)),
                        'ocr_instagram_username' => $instagramUsername,
                        'submitted_at' => now(),
                        'validation_status' => 'valid',
                    ]);

                    // Update cycle's current stage
                    if ($stage > $cycle->current_stage) {
                        $cycle->update(['current_stage' => $stage]);
                    }
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Extract all images from worksheet
     */
    protected function extractImages($worksheet)
    {
        $images = [];

        foreach ($worksheet->getDrawingCollection() as $drawing) {
            try {
                // Support both Drawing (file-based) and MemoryDrawing (embedded)
                if ($drawing instanceof Drawing) {
                    $imagePath = $drawing->getPath();
                    if (file_exists($imagePath)) {
                        $imageContents = file_get_contents($imagePath);
                        $extension = pathinfo($imagePath, PATHINFO_EXTENSION);

                        $images[] = [
                            'coordinates' => $drawing->getCoordinates(),
                            'content' => $imageContents,
                            'extension' => $extension ?: 'png',
                            'name' => $drawing->getName(),
                        ];
                    }
                } elseif ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing) {
                    // Handle embedded images
                    ob_start();
                    call_user_func($drawing->getRenderingFunction(), $drawing->getImageResource());
                    $imageContents = ob_get_contents();
                    ob_end_clean();

                    $images[] = [
                        'coordinates' => $drawing->getCoordinates(),
                        'content' => $imageContents,
                        'extension' => strtolower($drawing->getMimeType() === 'image/jpeg' ? 'jpg' : 'png'),
                        'name' => $drawing->getName(),
                    ];
                }
            } catch (\Exception $e) {
                // Skip images that fail to extract
                \Log::warning("Failed to extract image: " . $e->getMessage());
                continue;
            }
        }

        return $images;
    }

    /**
     * Find image for a specific cell
     */
    protected function findImageForCell($images, $cellCoordinate)
    {
        foreach ($images as $image) {
            if ($image['coordinates'] === $cellCoordinate) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Save image to storage
     */
    protected function saveImage($imageData, $merchantName, $stage)
    {
        $sanitizedName = Str::slug($merchantName);
        $timestamp = now()->format('YmdHis');
        $filename = "{$sanitizedName}_stage{$stage}_{$timestamp}.{$imageData['extension']}";

        $path = "screenshots/{$filename}";

        Storage::disk('public')->put($path, $imageData['content']);

        return $path;
    }

    /**
     * Clean cell value
     */
    protected function cleanValue($value)
    {
        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * Map category from spreadsheet to database format
     */
    protected function mapCategory($category)
    {
        if (empty($category)) {
            return 'umkm_fb';
        }

        $category = strtolower($category);

        $mapping = [
            'menengah' => 'umkm_fb',
            'kecil' => 'umkm_fb',
            'coffee' => 'coffee_shop',
            'coffee shop' => 'coffee_shop',
            'restoran' => 'restoran',
            'restaurant' => 'restoran',
        ];

        foreach ($mapping as $key => $value) {
            if (str_contains($category, $key)) {
                return $value;
            }
        }

        return 'umkm_fb';
    }

    /**
     * Map channel from spreadsheet to database format
     */
    protected function mapChannel($channel)
    {
        if (empty($channel)) {
            return 'instagram';
        }

        $channel = strtolower($channel);

        $mapping = [
            'ig' => 'instagram',
            'instagram' => 'instagram',
            'tiktok' => 'tiktok',
            'fb' => 'facebook',
            'facebook' => 'facebook',
            'wa' => 'whatsapp',
            'whatsapp' => 'whatsapp',
        ];

        return $mapping[$channel] ?? 'instagram';
    }

    /**
     * Map status from spreadsheet to cycle status
     */
    protected function mapStatus($status)
    {
        if (empty($status)) {
            return 'ongoing';
        }

        $status = strtolower($status);

        if (str_contains($status, 'reject') || str_contains($status, 'tolak')) {
            return 'rejected';
        }

        if (str_contains($status, 'convert') || str_contains($status, 'terima') || str_contains($status, 'closing')) {
            return 'converted';
        }

        return 'ongoing';
    }
}
