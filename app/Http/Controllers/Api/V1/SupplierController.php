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
}