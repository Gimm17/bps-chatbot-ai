<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ModelsController;
use Illuminate\Support\Facades\Route;

// Public, no auth. API key never exposed — all provider calls server-side.
// File api.php sudah di-prefix "api/" oleh Laravel, jangan tambah prefix lagi.
Route::get('/health', [HealthController::class, 'index']);
Route::get('/models', [ModelsController::class, 'index']);
Route::post('/chat', [ChatController::class, 'chat']);
Route::post('/feedback', [FeedbackController::class, 'store']);
