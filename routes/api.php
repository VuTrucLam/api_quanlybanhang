<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;

Route::prefix('v1')->group(function () {
    // Public API: Không yêu cầu xác thực
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']); // Thêm route login

    // Route testapi
    Route::get('/testapi', function () {
        return response()->json([
            'msg' => "thành công"
        ]);
    });

    // Các API được bảo vệ bởi Passport token
    Route::middleware('auth:api')->group(function () {
        Route::get('/user', function () {
            return response()->json(auth()->user());
        });
    });
});