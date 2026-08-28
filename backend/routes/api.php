<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\EvidenceController;
use App\Http\Controllers\Api\GangguanController;
use App\Http\Controllers\Api\SummaryController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/evidence/{evidence}/view', [EvidenceController::class, 'view']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:Agent,sanctum')->group(function () {
        Route::get('/agent/dashboard', [AuthController::class, 'agentDashboard']);
        Route::get('/agent/laporan/{gangguan}', [GangguanController::class, 'showForAgent']);
    });

    Route::middleware('role:Admin|TS,sanctum')->group(function () {
        Route::get('/gangguan', [GangguanController::class, 'index']);
        Route::get('/gangguan/{gangguan}', [GangguanController::class, 'show']);
        Route::delete('/gangguan/{gangguan}', [GangguanController::class, 'destroy']);
        Route::get('/users', [\App\Http\Controllers\Api\UserController::class, 'index']);
        Route::post('/users', [\App\Http\Controllers\Api\UserController::class, 'store']);
        Route::post('/users/import', [\App\Http\Controllers\Api\UserController::class, 'importAgents']);
        Route::delete('/users/{user}', [\App\Http\Controllers\Api\UserController::class, 'destroy']);
    });

    Route::middleware('role:Agent|TS|Admin,sanctum')->group(function () {
        Route::post('/gangguan', [GangguanController::class, 'store']);
    });

    Route::middleware('role:TS|Agent|Admin,sanctum')->group(function () {
        Route::put('/gangguan/{gangguan}', [GangguanController::class, 'update']);
        Route::patch('/gangguan/{gangguan}', [GangguanController::class, 'update']);
        Route::post('/gangguan/{gangguan}/upload', [EvidenceController::class, 'upload']);
        Route::delete('/evidence/{evidence}', [EvidenceController::class, 'destroy']);
    });

    Route::middleware('role:Admin|TS,sanctum')->group(function () {
        Route::get('/summary', [SummaryController::class, 'index']);
        Route::get('/summary/daily', [SummaryController::class, 'daily']);
        Route::get('/summary/weekly', [SummaryController::class, 'weekly']);
        Route::get('/summary/monthly', [SummaryController::class, 'monthly']);
    });

    Route::middleware('role:Admin,sanctum')->group(function () {
        Route::get('/backups', [BackupController::class, 'index']);
        Route::post('/backups/download', [BackupController::class, 'download']);
        Route::post('/backups/restore', [BackupController::class, 'restore']);
        Route::post('/backups/trigger', [BackupController::class, 'trigger']);

        Route::post('/cubicles/import', [\App\Http\Controllers\Api\CubicleController::class, 'import']);
    });
    
    Route::middleware('role:Agent|TS|Admin,sanctum')->group(function () {
        Route::get('/cubicles', [\App\Http\Controllers\Api\CubicleController::class, 'index']);
    });
});
