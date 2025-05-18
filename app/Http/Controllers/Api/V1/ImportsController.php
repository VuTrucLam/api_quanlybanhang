<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\ImportDetail;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportsController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'supplier_id' => 'required|integer|exists:suppliers,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.import_price' => 'required|numeric|min:0',
                'import_date' => 'nullable|date_format:Y-m-d',
            ]);

            // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
            return DB::transaction(function () use ($validated) {
                // Tính tổng giá trị nhập hàng (total_amount)
                $totalAmount = 0;
                foreach ($validated['products'] as $product) {
                    $totalAmount += $product['quantity'] * $product['import_price'];
                }

                // Tạo bản ghi nhập hàng
                $import = Import::create([
                    'warehouse_id' => $validated['warehouse_id'],
                    'supplier_id' => $validated['supplier_id'],
                    'total_amount' => $totalAmount,
                ]);

                // Lưu chi tiết nhập hàng và cập nhật tồn kho
                foreach ($validated['products'] as $product) {
                    $productId = $product['product_id'];
                    $quantity = $product['quantity'];
                    $importPrice = $product['import_price'];

                    // Cập nhật tồn kho trong products
                    $productRecord = Product::findOrFail($productId);
                    $productRecord->quantity += $quantity;
                    $productRecord->save();

                    // Lưu chi tiết nhập hàng
                    ImportDetail::create([
                        'import_id' => $import->id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_price' => $importPrice,
                    ]);
                }

                return response()->json([
                    'message' => 'Import recorded successfully',
                    'import_id' => $import->id,
                ], 200);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to record import.',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}