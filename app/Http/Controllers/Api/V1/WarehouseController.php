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
}
