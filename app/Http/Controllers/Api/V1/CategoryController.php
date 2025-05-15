<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function storeCategory(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name',
                'description' => 'sometimes|string|nullable',
            ]);

            // Tạo danh mục mới
            $category = Category::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]);

            return response()->json([
                'message' => 'Category created successfully',
                'category_id' => $category->id
            ], 201); // 201: Created
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create category.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
