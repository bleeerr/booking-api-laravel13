<?php

use App\Http\Controllers\Api\V1\AdminBookingController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');
    Route::get('/me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum');
    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');

    Route::get('/workspaces', [WorkspaceController::class, 'index']);
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show']);
    Route::post('/workspaces', [WorkspaceController::class, 'store'])
        ->middleware('auth:sanctum');
    Route::patch('/workspaces/{workspace}', [WorkspaceController::class, 'update'])
        ->middleware('auth:sanctum');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])
        ->middleware('auth:sanctum');

    Route::get('/workspaces/{workspace}/rooms', [RoomController::class, 'index']);
    Route::post('/workspaces/{workspace}/rooms', [RoomController::class, 'store'])
        ->middleware('auth:sanctum');
    Route::patch('/rooms/{room}', [RoomController::class, 'update'])
        ->middleware('auth:sanctum');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])
        ->middleware('auth:sanctum');
    Route::get('/rooms/{room}/availability', [RoomController::class, 'availability']);

    Route::get('/admin/bookings', [AdminBookingController::class, 'index'])
        ->middleware('auth:sanctum');

    Route::get('/bookings', [BookingController::class, 'index'])
        ->middleware('auth:sanctum');
    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware('auth:sanctum');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->middleware('auth:sanctum');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->middleware('auth:sanctum');
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware('auth:sanctum');
});
