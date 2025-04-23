<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ProductController;

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
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/profile/avatar', [AuthController::class, 'updateAvatar']);
        Route::put('/profile/update', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::delete('/profile/delete', [AuthController::class, 'deleteAccount']);


        //Product
        Route::post('/product/add', [ProductController::class, 'addProduct']);
        Route::get('/product/view/{id}', [ProductController::class, 'show']);
    });
});