<?php

namespace App\Http\Controllers\Api\V1;



use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Routing\Controller; // Import Controller

class ProductController extends Controller
{
    public function addProduct(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'photo' => 'nullable|string',
            'quantity' => 'nullable|numeric',
            'description' => 'nullable|string',
            'summary' => 'nullable|string',
            'price' => 'required|numeric', // Thêm required để đảm bảo price không null
            'cat_id' => 'nullable|numeric',
        ]);

        $data = $request->all();

        $slug = Str::slug($request->input('title'));
        $slug_count = Product::where('slug', $slug)->count();
        if ($slug_count > 0) {
            $slug .= time();
        }
        $data['slug'] = $slug;

        // Kiểm tra trùng lặp title (bỏ comment nếu cần)
        if (Product::where('title', $request->title)->exists()) {
            return response()->json(['message' => 'Sản phẩm đã có'], 409);
        }

        $product = Product::create($data);

        return response()->json([
            'product' => $product
        ], 201); // Sử dụng mã 201 cho hành động tạo mới
    }
    public function show($id)
    {
        $product = Product::where('slug',$id)->orWhere('id', $id)->first();
        return response()->json([
            'product' => $product
        ], 201);
    }
    public function getProducts(Request $request)
    {
        try {
            // Xác thực tham số đầu vào
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);

            // Đảm bảo page và limit là số nguyên dương
            if (!is_numeric($page) || $page < 1) {
                return response()->json(['error' => 'Page must be a positive integer.'], 422);
            }
            if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
                return response()->json(['error' => 'Limit must be between 1 and 100.'], 422);
            }

            // Lấy danh sách sản phẩm với phân trang
            $products = Product::select('id', 'title', 'slug', 'photo', 'quantity', 'description', 'summary', 'price', 'cat_id')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'products' => $products->items(),
                'total' => $products->total(),
                'page' => $products->currentPage(),
                'limit' => $products->perPage(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch products.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
