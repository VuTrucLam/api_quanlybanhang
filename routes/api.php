<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\FundController;

Route::prefix('v1')->group(function () {
    // Public API: Không yêu cầu xác thực
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']); // Thêm route login
    Route::get('/products', [ProductController::class, 'getProducts']);
    Route::get('/categories', [CategoryController::class, 'getCategories']);

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
        Route::get('/search-user', [AuthController::class, 'searchUser']);
        Route::get('/friends', [AuthController::class, 'getFriends']);
        Route::post('/add-friend', [AuthController::class, 'addFriend']);
        Route::delete('/friends/{id}', [AuthController::class, 'removeFriend']);
        Route::get('/friend-requests', [AuthController::class, 'getFriendRequests']);
        Route::post('/accept-friend', [AuthController::class, 'acceptFriend']);
        Route::post('/reject-friend', [AuthController::class, 'rejectFriend']);
        Route::post('/get-user-profiles', [AuthController::class, 'getUserProfiles']);


        //Product
        Route::post('/product/add', [ProductController::class, 'addProduct']);
        Route::get('/product/view/{id}', [ProductController::class, 'show']);
        Route::put('/products/{id}', [ProductController::class, 'updateProduct']);
        Route::delete('/products/{id}', [ProductController::class, 'deleteProduct']);
        Route::get('/products/search', [ProductController::class, 'searchProducts']);

        // categories
        Route::post('/categories', [CategoryController::class, 'storeCategory']);
        Route::get('/categories/{id}', [CategoryController::class, 'showCategory']);
        Route::put('/categories/{id}', [CategoryController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [CategoryController::class, 'deleteCategory']);

        //warehouses
        Route::get('/warehouses', [WarehouseController::class, 'getWarehouses']);
        Route::post('/warehouses', [WarehouseController::class, 'storeWarehouse']);

        //suppliers
        Route::get('/suppliers', [SupplierController::class, 'getSuppliers']);
        Route::post('/suppliers', [SupplierController::class, 'storeSupplier']);

        // inventory
        Route::get('/inventory', [InventoryController::class, 'getInventory']);
        Route::post('/inventory/import', [InventoryController::class, 'importInventory']);
        Route::get('/inventory/imports', [InventoryController::class, 'getImports']);
        Route::post('/inventory/export', [InventoryController::class, 'exportInventory']);
        Route::post('/inventory/check', [InventoryController::class, 'checkInventory']);
        Route::post('/inventory/transfer/internal', [InventoryController::class, 'internalTransfer']);
        Route::post('/inventory/transfer/repair', [InventoryController::class, 'repairTransfer']);
        Route::post('/inventory/transfer/discard', [InventoryController::class, 'discardTransfer']);
        Route::get('/inventory/discards', [InventoryController::class, 'getDiscards']);
        Route::get('/inventory/initial', [InventoryController::class, 'getInitialInventory']);

        // fund
        Route::get('/fund/revenue-types', [FundController::class, 'getRevenueTypes']);
        Route::post('/fund/revenue-types', [FundController::class, 'storeRevenueType']);
    });
});