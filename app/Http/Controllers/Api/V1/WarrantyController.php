<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WarrantyInventory;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    public function getWarrantyInventory(Request $request)
    {
        try {
            // Lấy tham số từ query
            $warehouseId = $request->query('warehouse_id');
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);

            // Validate tham số
            $validated = $request->validate([
                'warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'page' => 'integer|min:1',
                'limit' => 'integer|min:1|max:100',
            ]);

            $page = $validated['page'] ?? $page;
            $limit = $validated['limit'] ?? $limit;

            // Xây dựng truy vấn
            $query = WarrantyInventory::select('product_id', 'quantity', 'warehouse_id', 'warranty_status')
                ->with(['product' => function ($query) {
                    $query->select('id', 'title');
                }, 'warehouse' => function ($query) {
                    $query->select('id', 'name');
                }]);

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            // Tính tổng số bản ghi
            $total = $query->count();

            // Phân trang
            $products = $query->paginate($limit, ['*'], 'page', $page);

            // Định dạng phản hồi
            $formattedProducts = $products->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'title' => $item->product->title,
                    'quantity' => $item->quantity,
                    'warehouse_id' => $item->warehouse_id,
                    'warranty_status' => $item->warranty_status,
                    // 'warehouse_name' => $item->warehouse->name, // Nếu cần
                ];
            });

            return response()->json([
                'products' => $formattedProducts,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch warranty inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
