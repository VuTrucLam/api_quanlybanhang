<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;
use App\Models\Import;
use App\Models\ImportDetail;
use App\Models\Export;
use App\Models\ExportDetail;
use App\Models\InventoryCheck;
use App\Models\InventoryCheckDetail;
use App\Models\Transfer;
use App\Models\TransferDetail;

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
    public function getImports(Request $request)
    {
        try {
            // Lấy tham số từ query
            $warehouseId = $request->query('warehouse_id');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Xây dựng truy vấn
            $query = Import::query()
                ->select(
                    'id as import_id',
                    'warehouse_id',
                    'supplier_id',
                    'total_amount',
                    'created_at'
                );

            // Lọc theo warehouse_id nếu có
            if ($warehouseId) {
                if (!is_numeric($warehouseId) || $warehouseId < 1) {
                    return response()->json(['error' => 'Warehouse ID must be a positive integer.'], 400);
                }
                $query->where('warehouse_id', $warehouseId);
            }

            // Lọc theo khoảng thời gian nếu có
            if ($startDate) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !strtotime($startDate)) {
                    return response()->json(['error' => 'Start date must be in YYYY-MM-DD format.'], 400);
                }
                $query->where('created_at', '>=', $startDate . ' 00:00:00');
            }

            if ($endDate) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || !strtotime($endDate)) {
                    return response()->json(['error' => 'End date must be in YYYY-MM-DD format.'], 400);
                }
                $query->where('created_at', '<=', $endDate . ' 23:59:59');
            }

            // Kiểm tra start_date và end_date hợp lệ
            if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
                return response()->json(['error' => 'Start date must be before end date.'], 400);
            }

            // Lấy danh sách phiếu nhập kho
            $imports = $query->get();

            return response()->json($imports, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch imports.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function exportInventory(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
            ]);

            // Kiểm tra tồn kho trước khi xuất
            foreach ($validated['products'] as $index => $product) {
                $inventory = Inventory::where('product_id', $product['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                if (!$inventory || $inventory->quantity < $product['quantity']) {
                    return response()->json([
                        'error' => 'Insufficient quantity in inventory.',
                        'product_id' => $product['product_id'],
                        'available_quantity' => $inventory ? $inventory->quantity : 0,
                    ], 400);
                }
            }

            // Tạo phiếu xuất kho
            $export = Export::create([
                'warehouse_id' => $validated['warehouse_id'],
            ]);

            // Tạo chi tiết xuất kho và cập nhật tồn kho
            foreach ($validated['products'] as $product) {
                ExportDetail::create([
                    'export_id' => $export->id,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                ]);

                // Giảm số lượng trong tồn kho
                $inventory = Inventory::where('product_id', $product['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();
                $inventory->decrement('quantity', $product['quantity']);

                // Xóa bản ghi tồn kho nếu quantity = 0
                if ($inventory->quantity == 0) {
                    $inventory->delete();
                }
            }

            return response()->json([
                'message' => 'Export successful',
                'export_id' => $export->id
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to export inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function checkInventory(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.actual_quantity' => 'required|integer|min:0',
            ]);

            // Tạo phiếu kiểm kho
            $check = InventoryCheck::create([
                'warehouse_id' => $validated['warehouse_id'],
            ]);

            // Lưu chi tiết kiểm kho và xác định chênh lệch
            $discrepancies = [];
            foreach ($validated['products'] as $product) {
                InventoryCheckDetail::create([
                    'inventory_check_id' => $check->id,
                    'product_id' => $product['product_id'],
                    'actual_quantity' => $product['actual_quantity'],
                ]);

                // So sánh với số lượng tồn kho hiện tại
                $inventory = Inventory::where('product_id', $product['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                $currentQuantity = $inventory ? $inventory->quantity : 0;
                $difference = $product['actual_quantity'] - $currentQuantity;

                if ($difference != 0) {
                    $discrepancies[] = [
                        'product_id' => $product['product_id'],
                        'current_quantity' => $currentQuantity,
                        'actual_quantity' => $product['actual_quantity'],
                        'difference' => $difference,
                    ];
                }

                // Cập nhật tồn kho nếu có chênh lệch
                if ($inventory) {
                    $inventory->update(['quantity' => $product['actual_quantity']]);
                } elseif ($product['actual_quantity'] > 0) {
                    Inventory::create([
                        'product_id' => $product['product_id'],
                        'warehouse_id' => $validated['warehouse_id'],
                        'quantity' => $product['actual_quantity'],
                    ]);
                }
            }

            return response()->json([
                'message' => 'Inventory check completed',
                'check_id' => $check->id,
                'discrepancies' => $discrepancies,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function internalTransfer(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'from_warehouse_id' => 'required|exists:warehouses,id',
                'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
            ]);

            // Kiểm tra kho nguồn và kho đích khác nhau
            if ($validated['from_warehouse_id'] == $validated['to_warehouse_id']) {
                return response()->json(['error' => 'Source and destination warehouses must be different.'], 400);
            }

            // Kiểm tra tồn kho trước khi chuyển
            foreach ($validated['products'] as $index => $product) {
                $inventory = Inventory::where('product_id', $product['product_id'])
                    ->where('warehouse_id', $validated['from_warehouse_id'])
                    ->first();

                if (!$inventory || $inventory->quantity < $product['quantity']) {
                    return response()->json([
                        'error' => 'Insufficient quantity in source warehouse.',
                        'product_id' => $product['product_id'],
                        'available_quantity' => $inventory ? $inventory->quantity : 0,
                    ], 400);
                }
            }

            // Tạo phiếu chuyển kho
            $transfer = Transfer::create([
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'type' => 'internal',
                'reason' => 'Internal transfer',
            ]);

            // Tạo chi tiết chuyển kho và cập nhật tồn kho
            foreach ($validated['products'] as $product) {
                TransferDetail::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                ]);

                // Giảm số lượng trong kho nguồn
                $sourceInventory = Inventory::where('product_id', $product['product_id'])
                    ->where('warehouse_id', $validated['from_warehouse_id'])
                    ->first();
                $sourceInventory->decrement('quantity', $product['quantity']);

                // Xóa bản ghi kho nguồn nếu quantity = 0
                if ($sourceInventory->quantity == 0) {
                    $sourceInventory->delete();
                }

                // Tăng số lượng trong kho đích
                $destInventory = Inventory::firstOrCreate(
                    [
                        'product_id' => $product['product_id'],
                        'warehouse_id' => $validated['to_warehouse_id'],
                    ],
                    ['quantity' => 0]
                );
                $destInventory->increment('quantity', $product['quantity']);
            }

            return response()->json([
                'message' => 'Internal transfer successful',
                'transfer_id' => $transfer->id
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to transfer inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function repairTransfer(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'reason' => 'nullable|string',
            ]);

            // Kiểm tra tồn kho trước khi chuyển sửa chữa
            foreach ($validated['products'] as $index => $product) {
                $inventory = Inventory::where('product_id', $product['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                if (!$inventory || $inventory->quantity < $product['quantity']) {
                    return response()->json([
                        'error' => 'Insufficient quantity in warehouse.',
                        'product_id' => $product['product_id'],
                        'available_quantity' => $inventory ? $inventory->quantity : 0,
                    ], 400);
                }
            }

            // Tạo phiếu chuyển kho để sửa chữa
            $transfer = Transfer::create([
                'from_warehouse_id' => $validated['warehouse_id'],
                'to_warehouse_id' => null, // Không có kho đích, chuyển đến trung tâm sửa chữa
                'type' => 'repair',
                'reason' => $validated['reason'] ?? 'Repair transfer',
            ]);

            // Tạo chi tiết chuyển kho và cập nhật tồn kho
            foreach ($validated['products'] as $product) {
                TransferDetail::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                ]);

                // Giảm số lượng trong tồn kho
                $inventory = Inventory::where('product_id', $product['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();
                $inventory->decrement('quantity', $product['quantity']);

                // Xóa bản ghi tồn kho nếu quantity = 0
                if ($inventory->quantity == 0) {
                    $inventory->delete();
                }
            }

            return response()->json([
                'message' => 'Transfer for repair successful',
                'transfer_id' => $transfer->id
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to transfer for repair.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}