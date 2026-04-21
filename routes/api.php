<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\SheController;
use App\Http\Controllers\DashboardController;

use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/user/change-password', [AuthController::class, 'changePassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/metadata', [AuthController::class, 'getMetadata']);

Route::prefix('users')->group(function () {
    Route::get('/', [AdminController::class, 'users']);
    Route::post('/', [AdminController::class, 'storeUser']);
    Route::put('/{id}/status', [AdminController::class, 'updateStatus']);
    Route::post('/{id}/reset-password', [AdminController::class, 'resetPassword']);
    Route::delete('/{id}', [AdminController::class, 'deleteUser']);
});

Route::prefix('permissions')->group(function () {
    Route::get('/{id}', [AdminController::class, 'getPermissions']);
    Route::post('/{id}', [AdminController::class, 'savePermissions']);
});

Route::get('/audit-logs', [AdminController::class, 'auditLogs']);

Route::prefix('risks')->group(function () {
    Route::get('/', [RiskController::class, 'index']);
    Route::post('/', [RiskController::class, 'store']);
    Route::post('/update', [RiskController::class, 'update']);
    Route::delete('/{id}', [RiskController::class, 'destroy']);
});

Route::get('/risk-metadata', [RiskController::class, 'getMetadata']);
Route::post('/risk-controls', [RiskController::class, 'addControl']);

Route::prefix('she-events')->group(function () {
    Route::get('/', [SheController::class, 'index']);
    Route::post('/', [SheController::class, 'store']);
    Route::delete('/{id}', [SheController::class, 'destroy']);
});

Route::get('/she-metadata', [SheController::class, 'getMetadata']);

Route::get('/dashboard-stats', [DashboardController::class, 'stats']);

// Monte Carlo Analysis
Route::post('/monte-carlo/simulate', [App\Http\Controllers\MonteCarloController::class, 'simulate']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Internal System-to-System routes (IP & Secret Key protected)
Route::prefix('v1/internal')->middleware('internal.api')->group(function () {
    Route::get('/risks', [RiskController::class, 'index']);
    Route::post('/risks', [RiskController::class, 'store']);
});


