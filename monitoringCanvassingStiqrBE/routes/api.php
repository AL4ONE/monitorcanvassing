<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\QualityCheckController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Messages
    Route::post('/messages/upload', [MessageController::class, 'upload']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{id}', [MessageController::class, 'show']);
    Route::delete('/messages/{id}', [MessageController::class, 'destroy']);

    // Quality Check (Supervisor only)
    Route::middleware('role:supervisor')->group(function () {
        Route::get('/quality-checks', [QualityCheckController::class, 'index']);
        Route::get('/quality-checks/{id}', [QualityCheckController::class, 'show']);
        Route::post('/quality-checks/{id}/review', [QualityCheckController::class, 'review']);
    });
});

