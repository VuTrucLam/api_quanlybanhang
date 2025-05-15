<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function getWarehouses()
    {
        try {
            $warehouses = Warehouse::select('id', 'name', 'location', 'capacity')->get();

            return response()->json($warehouses, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch warehouses.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function storeWarehouse(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'capacity' => 'required|integer|min:1',
            ]);

            // Tạo kho mới
            $warehouse = Warehouse::create($validated);

            return response()->json([
                'message' => 'Warehouse created successfully',
                'warehouse_id' => $warehouse->id
            ], 201); // 201: Created
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create warehouse.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
