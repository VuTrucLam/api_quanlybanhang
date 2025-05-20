<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\Order;
use Illuminate\Http\Request;

class DebtsController extends Controller
{
    public function record(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'order_id' => 'required|integer|exists:orders,id',
                'remaining_amount' => 'required|numeric|min:0',
            ]);

            // Lấy thông tin đơn hàng
            $order = Order::findOrFail($validated['order_id']);

            // Kiểm tra trạng thái đơn hàng
            if ($order->status === 'paid') {
                return response()->json([
                    'error' => 'Invalid order status.',
                    'message' => 'Orders with paid status cannot be recorded as debt.',
                ], 400);
            }

            // Kiểm tra remaining_amount không lớn hơn total_amount
            if ($validated['remaining_amount'] > $order->total_amount) {
                return response()->json([
                    'error' => 'Validation failed.',
                    'message' => 'Remaining amount cannot exceed total amount of the order.',
                ], 400);
            }

            // Tạo bản ghi công nợ
            $debt = Debt::create([
                'order_id' => $validated['order_id'],
                'user_id' => $order->user_id,
                'remaining_amount' => $validated['remaining_amount'],
            ]);

            // Trả về phản hồi
            return response()->json([
                'message' => 'User debt recorded successfully',
                'debt_id' => $debt->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Order not found.',
                'message' => 'The specified order does not exist.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to record debt.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
