<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function getSuppliers()
    {
        try {
            $suppliers = Supplier::select('id', 'name', 'contact', 'address')->get();

            return response()->json($suppliers, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch suppliers.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function storeSupplier(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'contact' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
            ]);

            // Tạo nhà cung cấp mới
            $supplier = Supplier::create($validated);

            return response()->json([
                'message' => 'Supplier created successfully',
                'supplier_id' => $supplier->id
            ], 201); // 201: Created
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create supplier.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}