<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Product;
use App\Models\User;
use App\Models\ShippingCarrier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id', // Sử dụng user_id thay vì customer_id
                'shipping_carrier_id' => 'required|integer|exists:shipping_carriers,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.unit_price' => 'required|numeric|min:0',
                'sale_date' => 'nullable|date_format:Y-m-d',
            ]);

            // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
            return DB::transaction(function () use ($validated) {
                // Tính tổng giá trị bán hàng (total_amount)
                $totalAmount = 0;
                foreach ($validated['products'] as $product) {
                    $totalAmount += $product['quantity'] * $product['unit_price'];
                }

                // Kiểm tra tồn kho
                foreach ($validated['products'] as $product) {
                    $productRecord = Product::findOrFail($product['product_id']);
                    if ($productRecord->quantity < $product['quantity']) {
                        throw new \Exception("Insufficient stock for product ID {$product['product_id']}. Available: {$productRecord->quantity}, Requested: {$product['quantity']}");
                    }
                }

                // Tạo bản ghi bán hàng
                $sale = Sale::create([
                    'user_id' => $validated['user_id'],
                    'shipping_carrier_id' => $validated['shipping_carrier_id'],
                    'total_amount' => $totalAmount,
                    'sale_date' => $validated['sale_date'] ?? now(),
                ]);

                // Lưu chi tiết bán hàng và cập nhật tồn kho
                foreach ($validated['products'] as $product) {
                    $productId = $product['product_id'];
                    $quantity = $product['quantity'];
                    $unitPrice = $product['unit_price'];

                    // Giảm tồn kho trong products
                    $productRecord = Product::findOrFail($productId);
                    $productRecord->quantity -= $quantity;
                    $productRecord->save();

                    // Lưu chi tiết bán hàng
                    SaleDetail::create([
                        'sale_id' => $sale->id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                    ]);
                }

                return response()->json([
                    'message' => 'Sale recorded successfully',
                    'sale_id' => $sale->id,
                ], 200);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to record sale.',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}