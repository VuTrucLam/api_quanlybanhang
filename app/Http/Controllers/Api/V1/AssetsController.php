<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
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
}
