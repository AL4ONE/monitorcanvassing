<?php

namespace App\Http\Controllers;

use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    /**
     * Import data from spreadsheet
     */
    public function importSpreadsheet(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        try {
            $file = $request->file('file');

            // Store file temporarily
            $tempPath = $file->store('temp');
            $fullPath = storage_path('app/' . $tempPath);

            // Process import
            $importService = new ImportService(auth()->id());
            $result = $importService->importFromSpreadsheet($fullPath);

            // Clean up temp file
            Storage::delete($tempPath);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
