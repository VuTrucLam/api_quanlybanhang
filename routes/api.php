<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;

Route::prefix('v1')->group(function () {
    // Public API: Không yêu cầu xác thực
    Route::post('/register', [AuthController::class, 'register']);

    // Route testapi
    Route::get('/testapi', function () {
        return response()->json([
            'msg' => "thành công"
        ]);
    });

    // Các API được bảo vệ bởi Passport token
    Route::middleware('auth:api')->group(function () {
        // Ví dụ: API lấy thông tin người dùng (sẽ thêm sau)
        // Route::get('/user', [UserController::class, 'getUser']);
    });
});