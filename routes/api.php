<?php

use App\Http\Controllers\Api\AgentTicketController;
use App\Http\Controllers\Api\MemoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/memories', [MemoryController::class, 'index'])->middleware('ability:memory:read');
    Route::post('/memories', [MemoryController::class, 'store'])->middleware('ability:memory:write');
    Route::delete('/memories/{memory}', [MemoryController::class, 'destroy'])->middleware('ability:memory:write');

    Route::get('/tickets', [AgentTicketController::class, 'index'])->middleware('ability:tickets:read');
    Route::patch('/tickets/{number}/status', [AgentTicketController::class, 'updateStatus'])->middleware('ability:tickets:status');
});
