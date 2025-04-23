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
}
