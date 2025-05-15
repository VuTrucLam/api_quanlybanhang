<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function getInventory(Request $request)
    {
        try {
            // Lấy tham số từ query
            $warehouseId = $request->query('warehouse_id');
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);

            // Kiểm tra tham số page và limit
            if (!is_numeric($page) || $page < 1) {
                return response()->json(['error' => 'Page must be a positive integer.'], 400);
            }
            if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
                return response()->json(['error' => 'Limit must be between 1 and 100.'], 400);
            }

            // Truy vấn tồn kho
            $query = Inventory::query()
                ->join('products', 'inventory.product_id', '=', 'products.id')
                ->select(
                    'inventory.product_id',
                    'products.title as name',
                    'inventory.quantity',
                    'inventory.warehouse_id'
                );

            // Lọc theo warehouse_id nếu có
            if ($warehouseId) {
                $query->where('inventory.warehouse_id', $warehouseId);
            }

            // Phân trang
            $inventory = $query->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'inventory' => $inventory->items(),
                'total' => $inventory->total(),
                'page' => $inventory->currentPage(),
                'limit' => $inventory->perPage(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}