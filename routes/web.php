<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Add /login route with CORS middleware for frontend compatibility
Route::post('/login', [AuthController::class, 'login'])
    ->middleware(\App\Http\Middleware\Cors::class);

// Add /register route with CORS middleware
Route::post('/register', [AuthController::class, 'register'])
    ->middleware(\App\Http\Middleware\Cors::class);

Route::get('/', function () {
    return view('welcome');
});
