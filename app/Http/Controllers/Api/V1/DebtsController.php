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
    public function list(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'user_id' => 'nullable|integer|exists:users,id',
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            // Lấy giá trị mặc định cho page và limit
            $page = $validated['page'] ?? 1;
            $limit = $validated['limit'] ?? 10;

            // Tạo query cho debts
            $query = Debt::with('order');

            // Lọc theo user_id
            if (isset($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            }

            // Lọc theo khoảng thời gian
            if (isset($validated['start_date'])) {
                $query->whereDate('created_at', '>=', $validated['start_date']);
            }
            if (isset($validated['end_date'])) {
                $query->whereDate('created_at', '<=', $validated['end_date']);
            }

            // Tính tổng số bản ghi
            $total = $query->count();

            // Phân trang
            $debts = $query->offset(($page - 1) * $limit)
                          ->limit($limit)
                          ->get()
                          ->map(function ($debt) {
                              return [
                                  'debt_id' => $debt->id,
                                  'user_id' => $debt->user_id,
                                  'order_id' => $debt->order_id,
                                  'amount' => $debt->order->total_amount,
                                  'remaining_amount' => $debt->remaining_amount,
                                  'order_status' => $debt->order->status,
                                  'created_at' => $debt->created_at->toISOString(),
                                  'updated_at' => $debt->updated_at->toISOString(),
                              ];
                          });

            // Trả về phản hồi
            return response()->json([
                'debts' => $debts,
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
                'error' => 'Failed to retrieve debts.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
