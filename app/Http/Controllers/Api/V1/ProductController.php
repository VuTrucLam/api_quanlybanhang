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
    public function updateProduct(Request $request, $id)
    {
        try {
            // Tìm sản phẩm theo ID
            $product = Product::find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'slug' => 'sometimes|string|max:255|unique:products,slug,' . $id,
                'photo' => 'sometimes|string|nullable',
                'quantity' => 'sometimes|integer|min:0',
                'description' => 'sometimes|string|nullable',
                'summary' => 'sometimes|string|nullable',
                'price' => 'sometimes|numeric|min:0',
                'cat_id' => 'sometimes|integer|exists:categories,id',
            ]);

            // Cập nhật các trường chỉ khi được cung cấp
            if ($request->has('title')) {
                $product->title = $request->title;
            }
            if ($request->has('slug')) {
                $product->slug = $request->slug ?? Str::slug($request->title);
            }
            if ($request->has('photo')) {
                $product->photo = $request->photo;
            }
            if ($request->has('quantity')) {
                $product->quantity = $request->quantity;
            }
            if ($request->has('description')) {
                $product->description = $request->description;
            }
            if ($request->has('summary')) {
                $product->summary = $request->summary;
            }
            if ($request->has('price')) {
                $product->price = $request->price;
            }
            if ($request->has('cat_id')) {
                $product->cat_id = $request->cat_id;
            }

            // Lưu thay đổi
            $product->save();

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update product.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteProduct($id)
    {
        try {
            // Tìm sản phẩm theo ID
            $product = Product::find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found.'], 404);
            }

            // Xóa sản phẩm
            $product->delete();

            return response()->json([
                'message' => 'Product deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete product.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
