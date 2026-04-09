<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VoiceNoteController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login/key', [AuthController::class, 'verifyKey'])->middleware('api.key');
Route::post('/login/totp', [AuthController::class, 'verifyTotp']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('notes', VoiceNoteController::class);
    Route::post('/notes/{note}/process', [VoiceNoteController::class, 'process']);
    Route::post('/notes/{note}/send', [TicketController::class, 'send']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::patch('/tickets/{number}', [TicketController::class, 'updateStatus']);

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::post('/settings/tokens', [SettingsController::class, 'createToken']);
    Route::delete('/settings/tokens/{token}', [SettingsController::class, 'revokeToken']);

    Route::get('/types', fn () => response()->json(
        collect(config('herold.types'))->map(fn ($type) => Arr::only($type, ['label', 'icon', 'extra_fields', 'github_label']))
    ));
});

Route::get('/cron/work', [CronController::class, 'work'])->middleware('cron.auth');
