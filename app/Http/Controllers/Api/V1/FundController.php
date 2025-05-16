<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RevenueType;
use App\Models\Account;
use App\Models\Receipt;
use Illuminate\Http\Request;


class FundController extends Controller
{
    public function getRevenueTypes(Request $request)
    {
        try {
            $revenueTypes = RevenueType::select('id', 'name', 'category')->get();
            return response()->json($revenueTypes, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch revenue types.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function storeRevenueType(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category' => 'required|string|in:revenue,expense',
            ]);

            // Tạo loại thu chi mới
            $revenueType = RevenueType::create([
                'name' => $validated['name'],
                'category' => $validated['category'],
            ]);

            return response()->json([
                'message' => 'Revenue type created successfully',
                'id' => $revenueType->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create revenue type.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function storeAccount(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|string|in:cash,bank',
                'initial_balance' => 'nullable|numeric|min:0',
            ]);

            // Tạo tài khoản mới
            $account = Account::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'balance' => $validated['initial_balance'] ?? 0,
            ]);

            return response()->json([
                'message' => 'Account created successfully',
                'account_id' => $account->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create account.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getAccounts(Request $request)
    {
        try {
            // Lấy tham số phân trang
            $page = $request->query('page', 1); // Mặc định: 1
            $limit = $request->query('limit', 10); // Mặc định: 10

            // Xác thực tham số
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'limit' => 'integer|min:1|max:100',
            ]);

            $page = $validated['page'] ?? $page;
            $limit = $validated['limit'] ?? $limit;

            // Lấy tổng số tài khoản
            $total = Account::count();

            // Lấy danh sách tài khoản với phân trang
            $accounts = Account::select('id', 'name', 'balance', 'type', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'accounts' => $accounts->items(),
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch accounts.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getInitialBalance(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'account_id' => 'required|exists:accounts,id',
                'date' => 'required|date_format:Y-m-d',
            ]);

            $accountId = $validated['account_id'];
            $date = $validated['date'];

            // Lấy thông tin tài khoản
            $account = Account::findOrFail($accountId);

            // Giả định số dư đầu kỳ là initial_balance (hoặc balance tại thời điểm tạo)
            // Trong thực tế, cần tính toán dựa trên giao dịch (sẽ mở rộng sau)
            $initialBalance = $account->balance; // Hiện tại lấy balance hiện tại làm số dư đầu kỳ

            return response()->json([
                'account_id' => $accountId,
                'balance' => $initialBalance,
                'date' => $date,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch initial balance.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getReceipts(Request $request)
    {
        try {
            // Lấy tham số từ query
            $type = $request->query('type');
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Xây dựng truy vấn
            $query = Receipt::select('id', 'account_id', 'type', 'amount', 'category_id', 'created_at');

            // Lọc theo type nếu có
            if ($type) {
                $request->validate([
                    'type' => 'string|in:receipt,payment',
                ]);
                $query->where('type', $type);
            }

            // Lọc theo khoảng thời gian nếu có
            if ($startDate) {
                $request->validate([
                    'start_date' => 'date_format:Y-m-d',
                ]);
                $query->where('created_at', '>=', $startDate . ' 00:00:00');
            }

            if ($endDate) {
                $request->validate([
                    'end_date' => 'date_format:Y-m-d',
                ]);
                $query->where('created_at', '<=', $endDate . ' 23:59:59');
            }

            // Kiểm tra start_date <= end_date
            if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
                return response()->json(['error' => 'Start date must be before end date.'], 400);
            }

            // Lấy danh sách phiếu thu chi
            $receipts = $query->orderBy('created_at', 'desc')->get();

            return response()->json($receipts, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch receipts.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}