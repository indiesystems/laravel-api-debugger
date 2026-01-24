<?php

use Illuminate\Support\Facades\Route;
use IndieSystems\ApiDebugger\Controllers\ApiDebuggerController;

Route::get('/', [ApiDebuggerController::class, 'index'])->name('api-debugger.index');

// Logs
Route::get('/logs', [ApiDebuggerController::class, 'logs'])->name('api-debugger.logs');
Route::get('/logs/{log}', [ApiDebuggerController::class, 'show'])->name('api-debugger.logs.show');
Route::get('/logs/{log}/json', [ApiDebuggerController::class, 'showJson'])->name('api-debugger.logs.json');
Route::delete('/logs/{log}', [ApiDebuggerController::class, 'deleteLog'])->name('api-debugger.logs.delete');
Route::delete('/logs', [ApiDebuggerController::class, 'clearLogs'])->name('api-debugger.logs.clear');

// Sessions
Route::get('/sessions', [ApiDebuggerController::class, 'sessions'])->name('api-debugger.sessions');
Route::post('/sessions', [ApiDebuggerController::class, 'createSession'])->name('api-debugger.sessions.create');
Route::patch('/sessions/{session}/extend', [ApiDebuggerController::class, 'extendSession'])->name('api-debugger.sessions.extend');
Route::patch('/sessions/{session}/stop', [ApiDebuggerController::class, 'stopSession'])->name('api-debugger.sessions.stop');
Route::delete('/sessions/{session}', [ApiDebuggerController::class, 'deleteSession'])->name('api-debugger.sessions.delete');
