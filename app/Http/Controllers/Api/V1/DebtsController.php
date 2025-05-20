<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\Order;
use App\Models\DebtPayment;
use App\Models\DebtSupplier;
use App\Models\Import;
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
    public function update(Request $request, $id)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'remaining_amount' => 'nullable|numeric|min:0',
            ]);

            // Tìm bản ghi công nợ
            $debt = Debt::with('order')->findOrFail($id);

            // Nếu remaining_amount được cung cấp, kiểm tra hợp lệ
            if (isset($validated['remaining_amount'])) {
                // So sánh với total_amount từ orders
                if ($validated['remaining_amount'] > $debt->order->total_amount) {
                    return response()->json([
                        'error' => 'Validation failed.',
                        'message' => 'Remaining amount cannot exceed the total amount of the order.',
                    ], 400);
                }

                // Cập nhật remaining_amount
                $debt->remaining_amount = $validated['remaining_amount'];
            }

            // Lưu thay đổi
            $debt->save();

            // Trả về phản hồi
            return response()->json([
                'message' => 'User debt updated successfully',
                'debt_id' => $debt->id,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Debt not found.',
                'message' => 'The specified debt does not exist.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update debt.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function payment(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'debt_id' => 'required|integer|exists:debts,id',
                'amount' => 'required|numeric|min:0',
                'payment_date' => 'required|date_format:Y-m-d',
            ]);

            // Tìm bản ghi công nợ
            $debt = Debt::findOrFail($validated['debt_id']);

            // Kiểm tra amount không lớn hơn remaining_amount
            if ($validated['amount'] > $debt->remaining_amount) {
                return response()->json([
                    'error' => 'Validation failed.',
                    'message' => 'Payment amount cannot exceed the remaining debt amount.',
                ], 400);
            }

            // Tạo bản ghi thanh toán
            $payment = DebtPayment::create([
                'debt_id' => $validated['debt_id'],
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
            ]);

            // Cập nhật remaining_amount
            $debt->remaining_amount -= $validated['amount'];
            $debt->save();

            // Trả về phản hồi
            return response()->json([
                'message' => 'User debt payment recorded successfully',
                'payment_id' => $payment->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Debt not found.',
                'message' => 'The specified debt does not exist.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to record payment.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function report(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
                'user_id' => 'nullable|integer|exists:users,id',
            ]);

            // Lấy các debts trong khoảng thời gian
            $query = Debt::with('order');

            // Lọc theo user_id nếu có
            if (isset($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            }

            // Lọc theo khoảng thời gian
            $query->whereBetween('created_at', [$validated['start_date'], $validated['end_date']]);

            // Lấy danh sách debts
            $debts = $query->get();

            // Tính toán báo cáo
            $totalDebt = $debts->sum(function ($debt) {
                return $debt->order->total_amount ?? 0;
            });

            $totalRemaining = $debts->sum('remaining_amount');

            // Tính total_paid từ debt_payments
            $totalPaid = DebtPayment::whereIn('debt_id', $debts->pluck('id'))
                                  ->whereBetween('payment_date', [$validated['start_date'], $validated['end_date']])
                                  ->sum('amount');

            // Trả về phản hồi
            return response()->json([
                'user_debts' => [
                    'total_debt' => round($totalDebt, 2),
                    'total_remaining' => round($totalRemaining, 2),
                    'total_paid' => round($totalPaid, 2),
                ],
                'date_range' => [
                    'start' => $validated['start_date'],
                    'end' => $validated['end_date'],
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate report.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function recordSupplierDebt(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'import_id' => 'required|integer|exists:imports,id',
                'remaining_amount' => 'required|numeric|min:0',
            ]);

            // Lấy thông tin phiếu nhập hàng
            $import = Import::findOrFail($validated['import_id']);

            // Kiểm tra remaining_amount không lớn hơn total_amount
            if ($validated['remaining_amount'] > $import->total_amount) {
                return response()->json([
                    'error' => 'Validation failed.',
                    'message' => 'Remaining amount cannot exceed total amount of the import.',
                ], 400);
            }

            // Tạo bản ghi công nợ nhà cung cấp
            $debt = DebtSupplier::create([
                'import_id' => $validated['import_id'],
                'supplier_id' => $import->supplier_id,
                'remaining_amount' => $validated['remaining_amount'],
            ]);

            // Trả về phản hồi
            return response()->json([
                'message' => 'Supplier debt recorded successfully',
                'debt_id' => $debt->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Import not found.',
                'message' => 'The specified import does not exist.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to record supplier debt.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function listSupplierDebts(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'supplier_id' => 'nullable|integer|exists:suppliers,id',
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            // Lấy giá trị mặc định cho page và limit
            $page = $validated['page'] ?? 1;
            $limit = $validated['limit'] ?? 10;

            // Tạo query cho debts_supplier
            $query = DebtSupplier::with('import');

            // Lọc theo supplier_id
            if (isset($validated['supplier_id'])) {
                $query->where('supplier_id', $validated['supplier_id']);
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
                                  'import_id' => $debt->import_id,
                                  'supplier_id' => $debt->supplier_id,
                                  'warehouse_id' => $debt->import->warehouse_id,
                                  'amount' => $debt->import->total_amount,
                                  'remaining_amount' => $debt->remaining_amount,
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
                'error' => 'Failed to retrieve supplier debts.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateSupplierDebt(Request $request, $id)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'remaining_amount' => 'nullable|numeric|min:0',
            ]);

            // Tìm bản ghi công nợ
            $debt = DebtSupplier::with('import')->findOrFail($id);

            // Nếu remaining_amount được cung cấp, kiểm tra hợp lệ
            if (isset($validated['remaining_amount'])) {
                // So sánh với total_amount từ imports
                if ($validated['remaining_amount'] > $debt->import->total_amount) {
                    return response()->json([
                        'error' => 'Validation failed.',
                        'message' => 'Remaining amount cannot exceed the total amount of the import.',
                    ], 400);
                }

                // Cập nhật remaining_amount
                $debt->remaining_amount = $validated['remaining_amount'];
            }

            // Lưu thay đổi
            $debt->save();

            // Trả về phản hồi
            return response()->json([
                'message' => 'Supplier debt updated successfully',
                'debt_id' => $debt->id,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Debt not found.',
                'message' => 'The specified debt does not exist.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update supplier debt.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
