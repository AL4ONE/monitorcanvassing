<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\QualityCheckController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

// Debug Route for Migrations (TEMPORARY)
Route::get('/debug/migrate', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        return response()->json([
            'status' => 'success',
            'message' => 'Migration command executed.',
            'output' => Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

// Debug ping to verify JSON response
Route::get('/debug/ping', function () {
    return response()->json(['ok' => true]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Messages
    Route::post('/messages/upload', [MessageController::class, 'upload']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/active-prospects', [MessageController::class, 'getActiveProspects']);
    Route::get('/messages/{id}', [MessageController::class, 'show'])->where('id', '[0-9]+');
    Route::delete('/messages/{id}', [MessageController::class, 'destroy'])->where('id', '[0-9]+');

    // Bulk Import
    Route::post('/import/spreadsheet', [ImportController::class, 'importSpreadsheet']);
    Route::get('/import/template', [TemplateController::class, 'downloadTemplate']);

    // Quality Check (Supervisor only)
    Route::middleware('role:supervisor')->group(function () {
        Route::get('/quality-checks', [QualityCheckController::class, 'index']);
        Route::post('/quality-checks/approve-all', [QualityCheckController::class, 'approveAll']);
        Route::get('/quality-checks/{id}', [QualityCheckController::class, 'show']);
        Route::post('/quality-checks/{id}/review', [QualityCheckController::class, 'review']);

        // Canvassing Data Management
        Route::delete('/canvassing/cleanup-valid', [\App\Http\Controllers\CanvassingController::class, 'cleanupValid']);
        Route::get('/canvassing/report', [\App\Http\Controllers\CanvassingController::class, 'report']);
        Route::patch('/canvassing/{id}/status', [\App\Http\Controllers\CanvassingController::class, 'updateStatus']);
    });
});

// Version endpoint
Route::get('/v1.0/version', function () {
    return response()->json([
        'version' => 'v1.0',
        'date' => now()->toDateTimeString(),
    ]);
});