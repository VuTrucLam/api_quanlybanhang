<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RevenueType;
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
}