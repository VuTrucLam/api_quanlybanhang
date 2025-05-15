<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;
use App\Models\Import;
use App\Models\ImportDetail;

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
    public function importInventory(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.unit_price' => 'required|numeric|min:0',
            ]);

            // Tính tổng số tiền
            $totalAmount = 0;
            foreach ($validated['products'] as $product) {
                $totalAmount += $product['quantity'] * $product['unit_price'];
            }

            // Tạo phiếu nhập kho
            $import = Import::create([
                'warehouse_id' => $validated['warehouse_id'],
                'supplier_id' => $validated['supplier_id'],
                'total_amount' => $totalAmount,
            ]);

            // Tạo chi tiết nhập kho và cập nhật tồn kho
            foreach ($validated['products'] as $product) {
                ImportDetail::create([
                    'import_id' => $import->id,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'],
                ]);

                // Cập nhật hoặc tạo mới tồn kho
                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $product['product_id'], 'warehouse_id' => $validated['warehouse_id']],
                    ['quantity' => 0]
                );
                $inventory->increment('quantity', $product['quantity']);
            }

            return response()->json([
                'message' => 'Import successful',
                'import_id' => $import->id
            ], 201); // 201: Created
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to import inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}