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
}