<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShippingCarrier;
use Illuminate\Http\Request;

class ShippingCarriersController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
            ]);

            // Tạo nhà vận chuyển mới
            $shippingCarrier = ShippingCarrier::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
            ]);

            // Trả về phản hồi
            return response()->json([
                'message' => 'Shipping carrier created successfully',
                'data' => [
                    'id' => $shippingCarrier->id,
                    'name' => $shippingCarrier->name,
                    'phone' => $shippingCarrier->phone,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed.',
                'message' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create shipping carrier.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function index()
    {
        try {
            // Lấy tất cả nhà vận chuyển
            $shippingCarriers = ShippingCarrier::select('id', 'name', 'phone')->get();

            // Trả về danh sách
            return response()->json($shippingCarriers, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve shipping carriers.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
