<?php

namespace App\Http\Controllers;

use App\Models\CanvassingCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CanvassingController extends Controller
{
    /**
     * Delete all canvassing cycles with status 'success'
     */
    public function cleanupSuccess()
    {
        try {
            DB::beginTransaction();

            // Count records to be deleted for logging
            $count = CanvassingCycle::where('status', 'success')->count();
            
            // Delete records
            // Note: Cascade delete on messages table will handle the related messages
            CanvassingCycle::where('status', 'success')->delete();

            DB::commit();

            Log::info('Bulk delete success canvassing data', [
                'deleted_count' => $count,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Berhasil menghapus data canvassing yang sukses',
                'deleted_count' => $count
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cleanup success error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Gagal menghapus data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
