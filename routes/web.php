<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VoiceNoteController;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login/key', [AuthController::class, 'verifyKey'])->middleware('throttle:5,1');
Route::post('/login/totp', [AuthController::class, 'verifyTotp'])->middleware('throttle:5,1');
Route::post('/login/totp/setup', [AuthController::class, 'setupTotp']);
Route::post('/login/totp/confirm', [AuthController::class, 'confirmTotp']);
Route::post('/logout', [AuthController::class, 'logout']);

// Recovery
Route::get('/recovery', [AuthController::class, 'showRecovery'])->middleware('throttle:5,60');
Route::post('/recovery', [AuthController::class, 'processRecovery'])->middleware('throttle:5,60');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notes', [VoiceNoteController::class, 'index'])->name('notes.index');
    Route::get('/notes/create', [VoiceNoteController::class, 'create'])->name('notes.create');
    Route::post('/notes', [VoiceNoteController::class, 'store'])->name('notes.store')->middleware('throttle:10,60');
    Route::get('/notes/{note}', [VoiceNoteController::class, 'show'])->name('notes.show');
    Route::put('/notes/{note}', [VoiceNoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [VoiceNoteController::class, 'destroy'])->name('notes.destroy');

    Route::post('/notes/{note}/process', [VoiceNoteController::class, 'process'])->name('notes.process');
    Route::post('/notes/{note}/send', [VoiceNoteController::class, 'send'])->name('notes.send');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

    Route::get('/types', fn () => response()->json(
        app(\App\Services\MessageTypeRegistry::class)->all()
    ))->name('types');
});
