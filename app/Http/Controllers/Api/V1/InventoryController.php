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
use App\Models\Product;
use App\Models\Warehouse;

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
    public function discardTransfer(Request $request)
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

            // Kiểm tra tồn kho trước khi hủy
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

            // Tạo phiếu chuyển kho để hủy
            $transfer = Transfer::create([
                'from_warehouse_id' => $validated['warehouse_id'],
                'to_warehouse_id' => null, // Không có kho đích, chuyển để hủy
                'type' => 'discard',
                'reason' => $validated['reason'] ?? 'Discard transfer',
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
                'message' => 'Discard transfer successful',
                'discard_id' => $transfer->id
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to discard transfer.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getDiscards(Request $request)
    {
        try {
            // Lấy tham số từ query
            $warehouseId = $request->query('warehouse_id');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Xây dựng truy vấn
            $query = TransferDetail::query()
                ->join('transfers', 'transfer_details.transfer_id', '=', 'transfers.id')
                ->where('transfers.type', 'discard')
                ->select(
                    'transfers.id as discard_id',
                    'transfer_details.product_id',
                    'transfer_details.quantity',
                    'transfers.reason',
                    'transfers.created_at'
                );

            // Lọc theo warehouse_id nếu có
            if ($warehouseId) {
                if (!is_numeric($warehouseId) || $warehouseId < 1) {
                    return response()->json(['error' => 'Warehouse ID must be a positive integer.'], 400);
                }
                $query->where('transfers.from_warehouse_id', $warehouseId);
            }

            // Lọc theo khoảng thời gian nếu có
            if ($startDate) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !strtotime($startDate)) {
                    return response()->json(['error' => 'Start date must be in YYYY-MM-DD format.'], 400);
                }
                $query->where('transfers.created_at', '>=', $startDate . ' 00:00:00');
            }

            if ($endDate) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || !strtotime($endDate)) {
                    return response()->json(['error' => 'End date must be in YYYY-MM-DD format.'], 400);
                }
                $query->where('transfers.created_at', '<=', $endDate . ' 23:59:59');
            }

            // Kiểm tra start_date và end_date hợp lệ
            if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
                return response()->json(['error' => 'Start date must be before end date.'], 400);
            }

            // Lấy danh sách phiếu hủy
            $discards = $query->get();

            return response()->json($discards, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch discards.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getInitialInventory(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'warehouse_id' => 'nullable|exists:warehouses,id',
                'date' => 'required|date_format:Y-m-d',
            ]);

            $warehouseId = $validated['warehouse_id'] ?? null; // Sử dụng null nếu không có warehouse_id
            $startDate = $validated['date'] . ' 00:00:00';
            $endDate = now()->toDateTimeString(); // Thời điểm hiện tại: 2025-05-16 10:12:00

            // Lấy tất cả sản phẩm và tồn kho hiện tại
            $products = Product::query()
                ->select('products.id as product_id', 'products.title as name')
                ->get()
                ->keyBy('product_id');

            $initialInventory = [];
            foreach ($products as $productId => $product) {
                // Lấy danh sách warehouse_id áp dụng
                $warehouses = $warehouseId
                    ? [$warehouseId]
                    : Warehouse::pluck('id')->toArray();

                foreach ($warehouses as $whId) {
                    // Lấy tồn kho hiện tại
                    $currentInventory = Inventory::where('product_id', $productId)
                        ->where('warehouse_id', $whId)
                        ->first();

                    $currentQuantity = $currentInventory ? $currentInventory->quantity : 0;

                    // Tính toán giao dịch nhập từ date đến hiện tại
                    $imports = ImportDetail::join('imports', 'import_details.import_id', '=', 'imports.id')
                        ->where('imports.warehouse_id', $whId)
                        ->where('import_details.product_id', $productId)
                        ->whereBetween('imports.created_at', [$startDate, $endDate])
                        ->sum('import_details.quantity');

                    // Tính toán giao dịch xuất từ date đến hiện tại
                    $exports = ExportDetail::join('exports', 'export_details.export_id', '=', 'exports.id')
                        ->where('exports.warehouse_id', $whId)
                        ->where('export_details.product_id', $productId)
                        ->whereBetween('exports.created_at', [$startDate, $endDate])
                        ->sum('export_details.quantity');

                    // Tính toán giao dịch chuyển kho (internal, discard, repair)
                    $transfersOut = TransferDetail::join('transfers', 'transfer_details.transfer_id', '=', 'transfers.id')
                        ->where('transfers.from_warehouse_id', $whId)
                        ->where('transfer_details.product_id', $productId)
                        ->whereBetween('transfers.created_at', [$startDate, $endDate])
                        ->sum('transfer_details.quantity');

                    $transfersIn = TransferDetail::join('transfers', 'transfer_details.transfer_id', '=', 'transfers.id')
                        ->where('transfers.to_warehouse_id', $whId)
                        ->where('transfers.type', 'internal')
                        ->where('transfer_details.product_id', $productId)
                        ->whereBetween('transfers.created_at', [$startDate, $endDate])
                        ->sum('transfer_details.quantity');

                    // Tính toán kiểm kho (lấy giá trị kiểm cuối cùng trước date)
                    $lastCheck = InventoryCheckDetail::join('inventory_checks', 'inventory_check_details.inventory_check_id', '=', 'inventory_checks.id')
                        ->where('inventory_checks.warehouse_id', $whId)
                        ->where('inventory_check_details.product_id', $productId)
                        ->where('inventory_checks.created_at', '<', $startDate)
                        ->orderBy('inventory_checks.created_at', 'desc')
                        ->first();

                    // Tính tồn kho đầu kỳ
                    $initialQuantity = $currentQuantity;
                    $initialQuantity -= $imports; // Trừ nhập kho từ date đến nay
                    $initialQuantity += $exports; // Cộng xuất kho từ date đến nay
                    $initialQuantity += $transfersOut; // Cộng số lượng chuyển đi
                    $initialQuantity -= $transfersIn; // Trừ số lượng chuyển đến

                    if ($lastCheck) {
                        // Nếu có kiểm kho trước date, dùng giá trị kiểm kho gần nhất
                        $checkImports = ImportDetail::join('imports', 'import_details.import_id', '=', 'imports.id')
                            ->where('imports.warehouse_id', $whId)
                            ->where('import_details.product_id', $productId)
                            ->whereBetween('imports.created_at', [$lastCheck->created_at, $startDate])
                            ->sum('import_details.quantity');

                        $checkExports = ExportDetail::join('exports', 'export_details.export_id', '=', 'exports.id')
                            ->where('exports.warehouse_id', $whId)
                            ->where('export_details.product_id', $productId)
                            ->whereBetween('exports.created_at', [$lastCheck->created_at, $startDate])
                            ->sum('export_details.quantity');

                        $checkTransfersOut = TransferDetail::join('transfers', 'transfer_details.transfer_id', '=', 'transfers.id')
                            ->where('transfers.from_warehouse_id', $whId)
                            ->where('transfer_details.product_id', $productId)
                            ->whereBetween('transfers.created_at', [$lastCheck->created_at, $startDate])
                            ->sum('transfer_details.quantity');

                        $checkTransfersIn = TransferDetail::join('transfers', 'transfer_details.transfer_id', '=', 'transfers.id')
                            ->where('transfers.to_warehouse_id', $whId)
                            ->where('transfers.type', 'internal')
                            ->where('transfer_details.product_id', $productId)
                            ->whereBetween('transfers.created_at', [$lastCheck->created_at, $startDate])
                            ->sum('transfer_details.quantity');

                        $initialQuantity = $lastCheck->actual_quantity;
                        $initialQuantity += $checkImports;
                        $initialQuantity -= $checkExports;
                        $initialQuantity -= $checkTransfersOut;
                        $initialQuantity += $checkTransfersIn;
                    }

                    // Chỉ thêm vào kết quả nếu quantity > 0
                    if ($initialQuantity > 0) {
                        $initialInventory[] = [
                            'product_id' => $productId,
                            'name' => $product->name,
                            'quantity' => $initialQuantity,
                            'warehouse_id' => $whId,
                        ];
                    }
                }
            }

            return response()->json($initialInventory, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch initial inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}