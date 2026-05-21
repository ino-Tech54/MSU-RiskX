<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\SheController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LossEventController;
use App\Http\Controllers\BcmController;
use App\Http\Controllers\ReportsController;

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
    Route::post('/approve', [RiskController::class, 'approve']);
    Route::post('/reject', [RiskController::class, 'reject']);
    Route::post('/import', [RiskController::class, 'importCsv']);
    Route::get('/due-reviews', [RiskController::class, 'dueReviews']);
    Route::delete('/{id}', [RiskController::class, 'destroy']);
});

Route::get('/risk-metadata', [RiskController::class, 'getMetadata']);
Route::get('/risk-controls', [RiskController::class, 'controls']);
Route::post('/risk-controls', [RiskController::class, 'addControl']);
Route::delete('/risk-controls/{id}', [RiskController::class, 'destroyControl']);

Route::prefix('she-events')->group(function () {
    Route::get('/', [SheController::class, 'index']);
    Route::post('/', [SheController::class, 'store']);
    Route::post('/import', [SheController::class, 'importCsv']);
    Route::delete('/{id}', [SheController::class, 'destroy']);
});

Route::get('/she-metadata', [SheController::class, 'getMetadata']);

Route::get('/dashboard-stats', [DashboardController::class, 'stats']);

// Monte Carlo Analysis
Route::post('/monte-carlo/simulate', [App\Http\Controllers\MonteCarloController::class, 'simulate']);

Route::prefix('loss-events')->group(function () {
    Route::get('/', [LossEventController::class, 'index']);
    Route::post('/', [LossEventController::class, 'store']);
    Route::post('/import', [LossEventController::class, 'import']);
    Route::put('/{id}', [LossEventController::class, 'update']);
    Route::delete('/{id}', [LossEventController::class, 'destroy']);
});
Route::get('/loss-metadata', [LossEventController::class, 'metadata']);

Route::prefix('insurance-claims')->group(function () {
    Route::get('/', [InsuranceClaimController::class, 'index']);
    Route::post('/', [InsuranceClaimController::class, 'store']);
    Route::post('/import', [InsuranceClaimController::class, 'import']);
    Route::get('/{id}', [InsuranceClaimController::class, 'show']);
    Route::put('/{id}', [InsuranceClaimController::class, 'update']);
    Route::delete('/{id}', [InsuranceClaimController::class, 'destroy']);
    Route::post('/{id}/documents', [InsuranceClaimController::class, 'uploadDocument']);
    Route::delete('/{id}/documents/{documentId}', [InsuranceClaimController::class, 'deleteDocument']);
});
Route::get('/insurance-metadata', [InsuranceClaimController::class, 'metadata']);

Route::prefix('bcm-plans')->group(function () {
    Route::get('/', [BcmController::class, 'index']);
    Route::post('/', [BcmController::class, 'store']);
    Route::put('/{id}', [BcmController::class, 'update']);
    Route::delete('/{id}', [BcmController::class, 'destroy']);
});
Route::get('/bcm-metadata', [BcmController::class, 'metadata']);

Route::get('/reports/summary', [ReportsController::class, 'summary']);
Route::get('/reports/risks', [ReportsController::class, 'risks']);
Route::get('/reports/she-events', [ReportsController::class, 'sheEvents']);
Route::get('/reports/loss-events', [ReportsController::class, 'lossEvents']);
Route::get('/reports/bcm-plans', [ReportsController::class, 'bcmPlans']);
Route::get('/reports/audit-trail', [ReportsController::class, 'auditTrail']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Internal System-to-System routes (IP & Secret Key protected)
Route::prefix('v1/internal')->middleware('internal.api')->group(function () {
    Route::get('/risks', [RiskController::class, 'index']);
});


