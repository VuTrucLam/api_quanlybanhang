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
    public function showCategory($id)
    {
        try {
            // Tìm danh mục theo ID
            $category = Category::find($id);

            if (!$category) {
                return response()->json(['message' => 'Category not found.'], 404);
            }

            // Trả về thông tin danh mục
            return response()->json([
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch category.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getCategories(Request $request)
    {
        try {
            // Lấy tham số page và limit
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);

            // Kiểm tra page và limit hợp lệ
            if (!is_numeric($page) || $page < 1) {
                return response()->json(['error' => 'Page must be a positive integer.'], 400);
            }
            if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
                return response()->json(['error' => 'Limit must be between 1 and 100.'], 400);
            }

            // Lấy danh sách danh mục với phân trang
            $categories = Category::select('id', 'name', 'description', 'created_at')
                ->paginate($limit, ['*'], 'page', $page);

            return response()->json([
                'categories' => $categories->items(),
                'total' => $categories->total(),
                'page' => $categories->currentPage(),
                'limit' => $categories->perPage(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch categories.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateCategory(Request $request, $id)
    {
        try {
            // Tìm danh mục theo ID
            $category = Category::find($id);

            if (!$category) {
                return response()->json(['message' => 'Category not found.'], 404);
            }

            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|unique:categories,name,' . $id,
                'description' => 'sometimes|string|nullable',
            ]);

            // Cập nhật các trường chỉ khi được cung cấp
            if ($request->has('name')) {
                $category->name = $validated['name'];
            }
            if ($request->has('description')) {
                $category->description = $validated['description'];
            }

            // Lưu thay đổi
            $category->save();

            return response()->json([
                'message' => 'Category updated successfully',
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update category.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteCategory($id)
    {
        try {
            // Tìm danh mục theo ID
            $category = Category::find($id);

            if (!$category) {
                return response()->json(['message' => 'Category not found.'], 404);
            }

            // Kiểm tra xem danh mục có sản phẩm hay không
            if ($category->products()->count() > 0) {
                return response()->json(['message' => 'Cannot delete category because it contains products.'], 400);
            }

            // Xóa danh mục
            $category->delete();

            return response()->json([
                'message' => 'Category deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete category.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
