<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


// Add /login and /register routes (CORS handled by global middleware)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/', function () {
    return view('welcome');
});
