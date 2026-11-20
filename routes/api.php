<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PublicTicketController;
use App\Http\Controllers\Api\WorkOrderController;
use Illuminate\Support\Facades\Route;

// Public / Guest API endpoints
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/public/summary', [PublicTicketController::class, 'summary']);
Route::get('/public/work-orders', [PublicTicketController::class, 'index']);
Route::get('/public/work-orders/{id}', [PublicTicketController::class, 'show'])->whereNumber('id');

// Protected Admin API endpoints (Sanctum Token Auth)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);

    // Admin Dashboard & Work Orders
    Route::get('/admin/summary', [PublicTicketController::class, 'summary']);
    Route::get('/admin/work-orders', [PublicTicketController::class, 'index']);
    Route::get('/admin/work-orders/export', [WorkOrderController::class, 'export']);
    Route::post('/admin/work-orders/parse', [WorkOrderController::class, 'parsePreview']);
    Route::post('/admin/work-orders', [WorkOrderController::class, 'store']);
    Route::get('/admin/work-orders/{id}', [PublicTicketController::class, 'show'])->whereNumber('id');
    Route::put('/admin/work-orders/{id}', [WorkOrderController::class, 'update'])->whereNumber('id');
    Route::post('/admin/work-orders/{id}/done', [WorkOrderController::class, 'markDone'])->whereNumber('id');
    Route::delete('/admin/work-orders/{id}', [WorkOrderController::class, 'destroy'])->whereNumber('id');

    // Departments
    Route::get('/admin/departments', [DepartmentController::class, 'index']);
    Route::post('/admin/departments', [DepartmentController::class, 'store']);
    Route::put('/admin/departments/{id}', [DepartmentController::class, 'update'])->whereNumber('id');
    Route::delete('/admin/departments/{id}', [DepartmentController::class, 'destroy'])->whereNumber('id');

    // Admin Management
    Route::get('/admin/admins', [AdminController::class, 'index']);
    Route::post('/admin/admins', [AdminController::class, 'store']);
    Route::put('/admin/admins/{id}', [AdminController::class, 'update'])->whereNumber('id');
    Route::delete('/admin/admins/{id}', [AdminController::class, 'destroy'])->whereNumber('id');
});
