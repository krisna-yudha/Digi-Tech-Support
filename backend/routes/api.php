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
        Route::get('/ts/my-stats', [GangguanController::class, 'myTsStats']);
        Route::get('/users', [\App\Http\Controllers\Api\UserController::class, 'index']);
        Route::post('/users', [\App\Http\Controllers\Api\UserController::class, 'store']);
        Route::post('/users/import/preview', [\App\Http\Controllers\Api\UserController::class, 'previewImport']);
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

        // Settings (baca) & Tanda Tangan Sendiri — bisa diakses Admin dan TS
        Route::get('/settings', [\App\Http\Controllers\Api\SettingController::class, 'index']);
        Route::post('/users/upload-signature', [\App\Http\Controllers\Api\UserController::class, 'uploadSignature']);
        Route::delete('/users/signature', [\App\Http\Controllers\Api\UserController::class, 'deleteSignature']);
    });

    Route::middleware('role:Admin,sanctum')->group(function () {
        Route::get('/backups', [BackupController::class, 'index']);
        Route::post('/backups/download', [BackupController::class, 'download']);
        Route::post('/backups/restore', [BackupController::class, 'restore']);
        Route::post('/backups/trigger', [BackupController::class, 'trigger']);

        Route::post('/cubicles/import/preview', [\App\Http\Controllers\Api\CubicleController::class, 'previewImport']);
        Route::post('/cubicles/import', [\App\Http\Controllers\Api\CubicleController::class, 'importBatch']);

        Route::post('/settings', [\App\Http\Controllers\Api\SettingController::class, 'update']);
        Route::post('/settings/upload-logo', [\App\Http\Controllers\Api\SettingController::class, 'uploadLogo']);
        Route::delete('/settings/logo', [\App\Http\Controllers\Api\SettingController::class, 'deleteLogo']);
        Route::post('/settings/upload-koord-signature', [\App\Http\Controllers\Api\SettingController::class, 'uploadKoordSignature']);
        Route::delete('/settings/koord-signature', [\App\Http\Controllers\Api\SettingController::class, 'deleteKoordSignature']);
    });
    
    Route::middleware('role:Agent|TS|Admin,sanctum')->group(function () {
        Route::get('/cubicles', [\App\Http\Controllers\Api\CubicleController::class, 'index']);
    });
});

