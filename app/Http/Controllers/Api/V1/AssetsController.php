<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferDetail;
use Illuminate\Http\Request;

class AssetsController extends Controller
{
    public function sell(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            // Lấy giá trị mặc định cho page và limit
            $page = $validated['page'] ?? 1;
            $limit = $validated['limit'] ?? 10;

            // Tạo query
            $query = Product::select(
                'id as product_id',
                'title',
                'quantity',
                'price',
                'cat_id'
            );

            // Tính tổng số sản phẩm (total_products)
            $totalProducts = Product::count();

            // Tính tổng số lượng (total_quantity)
            $totalQuantity = Product::sum('quantity');

            // Phân trang
            $products = $query->offset(($page - 1) * $limit)
                             ->limit($limit)
                             ->get();

            // Trả về phản hồi
            return response()->json([
                'products' => $products,
                'total_products' => $totalProducts,
                'total_quantity' => $totalQuantity,
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
                'error' => 'Failed to retrieve products.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function repair(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            // Lấy giá trị mặc định cho page và limit
            $page = $validated['page'] ?? 1;
            $limit = $validated['limit'] ?? 10;

            // Tạo query cho transfers
            $query = Transfer::where('type', 'internal')
                            ->with(['details' => function ($q) {
                                $q->with('product');
                            }]);

            // Lọc theo warehouse_id (to_warehouse_id)
            if (isset($validated['warehouse_id'])) {
                $query->where('to_warehouse_id', $validated['warehouse_id']);
            }

            // Lọc theo khoảng thời gian
            if (isset($validated['start_date'])) {
                $query->whereDate('created_at', '>=', $validated['start_date']);
            }
            if (isset($validated['end_date'])) {
                $query->whereDate('created_at', '<=', $validated['end_date']);
            }

            // Tính tổng số chuyển kho
            $total = $query->count();

            // Phân trang
            $transfers = $query->offset(($page - 1) * $limit)
                              ->limit($limit)
                              ->get()
                              ->map(function ($transfer) {
                                  $products = $transfer->details->map(function ($detail) {
                                      return [
                                          'product_id' => $detail->product_id,
                                          'title' => $detail->product->title,
                                          'quantity' => $detail->quantity,
                                      ];
                                  });

                                  return [
                                      'transfer_id' => $transfer->id,
                                      'type' => $transfer->type . ' transfer',
                                      'reason' => $transfer->reason,
                                      'products' => $products,
                                      'created_at' => $transfer->created_at->toISOString(),
                                  ];
                              });

            // Tính tổng số sản phẩm duy nhất (total_products)
            $totalProducts = TransferDetail::whereIn('transfer_id', $query->pluck('id'))
                                          ->distinct('product_id')
                                          ->count('product_id');

            // Tính tổng số lượng (total_quantity)
            $totalQuantity = TransferDetail::whereIn('transfer_id', $query->pluck('id'))
                                          ->sum('quantity');

            // Trả về phản hồi
            return response()->json([
                'transfers' => $transfers,
                'total_products' => $totalProducts,
                'total_quantity' => $totalQuantity,
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
                'error' => 'Failed to retrieve transfers.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
