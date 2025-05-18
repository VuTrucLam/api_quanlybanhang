<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Models\ShippingCarrier;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.unit_price' => 'required|numeric|min:0',
                'shipping_carrier_id' => 'nullable|integer|exists:shipping_carriers,id',
                'shipping_address' => 'nullable|string|max:255',
            ]);

            // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
            return DB::transaction(function () use ($validated) {
                // Tính tổng giá trị đơn hàng
                $totalAmount = 0;
                foreach ($validated['products'] as $product) {
                    $totalAmount += $product['quantity'] * $product['unit_price'];
                }

                // Kiểm tra tồn kho (chỉ kiểm tra, không giảm ngay)
                foreach ($validated['products'] as $product) {
                    $productRecord = Product::findOrFail($product['product_id']);
                    if ($productRecord->quantity < $product['quantity']) {
                        throw new \Exception("Insufficient stock for product ID {$product['product_id']}. Available: {$productRecord->quantity}, Requested: {$product['quantity']}");
                    }
                }

                // Tạo đơn hàng
                $order = Order::create([
                    'user_id' => $validated['user_id'],
                    'shipping_carrier_id' => $validated['shipping_carrier_id'],
                    'shipping_address' => $validated['shipping_address'],
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                // Lưu chi tiết đơn hàng
                foreach ($validated['products'] as $product) {
                    OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $product['product_id'],
                        'quantity' => $product['quantity'],
                        'unit_price' => $product['unit_price'],
                    ]);
                }

                return response()->json([
                    'message' => 'Order created successfully',
                    'order_id' => $order->id,
                ], 200);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create order.',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
