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
    public function index(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'status' => 'nullable|string|in:pending,confirmed,shipped,delivered,cancelled',
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            // Lấy giá trị mặc định cho page và limit
            $page = $validated['page'] ?? 1;
            $limit = $validated['limit'] ?? 10;

            // Tạo query
            $query = Order::select('id as order_id', 'user_id', 'total_amount', 'status', 'shipping_carrier_id', 'created_at');

            // Lọc theo trạng thái
            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            // Lọc theo khoảng thời gian
            if (isset($validated['start_date'])) {
                $query->whereDate('created_at', '>=', $validated['start_date']);
            }
            if (isset($validated['end_date'])) {
                $query->whereDate('created_at', '<=', $validated['end_date']);
            }

            // Tính tổng số đơn hàng
            $total = $query->count();

            // Phân trang
            $orders = $query->offset(($page - 1) * $limit)
                           ->limit($limit)
                           ->get();

            // Trả về phản hồi
            return response()->json([
                'orders' => $orders,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve orders.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function update(Request $request, $id)
    {
        try {
            // Tìm đơn hàng
            $order = Order::find($id);
            if (!$order) {
                return response()->json([
                    'error' => 'Order not found.',
                ], 404);
            }

            // Validate tham số
            $validated = $request->validate([
                'status' => 'nullable|string|in:pending,confirmed,shipped,delivered,cancelled',
                'shipping_address' => 'nullable|string|max:255',
                'shipping_carrier_id' => 'nullable|integer|exists:shipping_carriers,id',
            ]);

            // Cập nhật các trường nếu có
            if (isset($validated['status'])) {
                $order->status = $validated['status'];
            }
            if (isset($validated['shipping_address'])) {
                $order->shipping_address = $validated['shipping_address'];
            }
            if (isset($validated['shipping_carrier_id'])) {
                $order->shipping_carrier_id = $validated['shipping_carrier_id'];
            }

            // Lưu thay đổi
            $order->save();

            // Trả về phản hồi
            return response()->json([
                'message' => 'Order updated successfully',
                'order' => [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'shipping_carrier_id' => $order->shipping_carrier_id,
                    'created_at' => $order->created_at->toISOString(),
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update order.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function destroy($id)
    {
        try {
            // Tìm đơn hàng
            $order = Order::find($id);
            if (!$order) {
                return response()->json([
                    'error' => 'Order not found.',
                ], 404);
            }

            // Kiểm tra trạng thái đơn hàng
            if ($order->status !== 'pending') {
                return response()->json([
                    'error' => 'Order has been processed and cannot be cancelled.',
                ], 400);
            }

            // Cập nhật trạng thái thành cancelled
            $order->status = 'cancelled';
            $order->save();

            // Trả về phản hồi
            return response()->json([
                'message' => 'Order cancelled successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to cancel order.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function confirm($id)
    {
        try {
            // Tìm đơn hàng
            $order = Order::with('details')->find($id);
            if (!$order) {
                return response()->json([
                    'error' => 'Order not found.',
                ], 404);
            }

            // Kiểm tra trạng thái
            if (!in_array($order->status, ['pending'])) {
                return response()->json([
                    'error' => 'Order has already been confirmed or cancelled.',
                ], 400);
            }

            // Bắt đầu transaction để đảm bảo tính toàn vẹn
            return DB::transaction(function () use ($order) {
                // Cập nhật trạng thái đơn hàng
                $order->status = 'confirmed';
                $order->save();

                // Giảm tồn kho sản phẩm
                foreach ($order->details as $detail) {
                    $product = Product::findOrFail($detail->product_id);
                    if ($product->quantity < $detail->quantity) {
                        throw new \Exception("Insufficient stock for product ID {$detail->product_id}.");
                    }
                    $product->quantity -= $detail->quantity;
                    $product->save();
                }

                return response()->json([
                    'message' => 'Order confirmed successfully',
                ], 200);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to confirm order.',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
