<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WarrantyInventory;
use App\Models\WarrantyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transfer;
use App\Models\TransferDetail;
use App\Models\Product;

class WarrantyController extends Controller
{
    public function getWarrantyInventory(Request $request)
    {
        try {
            // Lấy tham số từ query
            $warehouseId = $request->query('warehouse_id');
            $page = $request->query('page', 1);
            $limit = $request->query('limit', 10);

            // Validate tham số
            $validated = $request->validate([
                'warehouse_id' => 'nullable|integer|exists:warehouses,id',
                'page' => 'integer|min:1',
                'limit' => 'integer|min:1|max:100',
            ]);

            $page = $validated['page'] ?? $page;
            $limit = $validated['limit'] ?? $limit;

            // Xây dựng truy vấn
            $query = WarrantyInventory::select('product_id', 'quantity', 'warehouse_id', 'warranty_status')
                ->with(['product' => function ($query) {
                    $query->select('id', 'title');
                }, 'warehouse' => function ($query) {
                    $query->select('id', 'name');
                }]);

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            // Tính tổng số bản ghi
            $total = $query->count();

            // Phân trang
            $products = $query->paginate($limit, ['*'], 'page', $page);

            // Định dạng phản hồi
            $formattedProducts = $products->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'title' => $item->product->title,
                    'quantity' => $item->quantity,
                    'warehouse_id' => $item->warehouse_id,
                    'warranty_status' => $item->warranty_status,
                    // 'warehouse_name' => $item->warehouse->name, // Nếu cần
                ];
            });

            return response()->json([
                'products' => $formattedProducts,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch warranty inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function addWarrantyInventory(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.warranty_status' => 'required|string|in:pending,processed',
            ]);

            $warehouseId = $validated['warehouse_id'];
            $products = $validated['products'];

            // Xử lý từng sản phẩm
            foreach ($products as $product) {
                $inventory = WarrantyInventory::firstOrNew([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $product['product_id'],
                ]);
                $inventory->quantity = ($inventory->quantity ?? 0) + $product['quantity'];
                $inventory->warranty_status = $product['warranty_status'];
                $inventory->save();
            }

            return response()->json([
                'message' => 'Products added to warranty inventory successfully',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add products to warranty inventory.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getWarrantyReceived(Request $request)
    {
        try {
            // Lấy tham số từ query
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Xây dựng truy vấn
            $query = WarrantyRequest::select('id', 'product_id', 'customer_id', 'received_date', 'issue_description')
                ->with(['product' => function ($query) {
                    $query->select('id', 'title');
                }, 'user' => function ($query) {
                    $query->select('id', 'name', 'email');
                }])
                ->whereNotNull('received_date');

            // Lọc theo start_date và end_date
            if ($startDate) {
                $request->validate([
                    'start_date' => 'date_format:Y-m-d',
                ]);
                $query->where('received_date', '>=', $startDate . ' 00:00:00');
            }

            if ($endDate) {
                $request->validate([
                    'end_date' => 'date_format:Y-m-d',
                ]);
                $query->where('received_date', '<=', $endDate . ' 23:59:59');
            }

            // Kiểm tra start_date <= end_date
            if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
                return response()->json(['error' => 'Start date must be before end date.'], 400);
            }

            // Lấy danh sách yêu cầu
            $requests = $query->orderBy('received_date', 'desc')->get();

            // Định dạng phản hồi
            $formattedRequests = $requests->map(function ($item) {
                return [
                    'request_id' => $item->id,
                    'product_id' => $item->product_id,
                    'title' => $item->product->title,
                    'customer_id' => $item->customer_id,
                    'received_date' => \Carbon\Carbon::parse($item->received_date)->toIso8601String(),
                    'issue_description' => $item->issue_description,
                ];
            });

            return response()->json($formattedRequests, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch warranty received requests.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function receiveWarranty(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'customer_id' => 'required|integer|exists:users,id', // Sử dụng users thay customers
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'issue_description' => 'required|string',
                'received_date' => 'nullable|date_format:Y-m-d H:i:s',
            ]);

            // Tạo yêu cầu bảo hành
            $warrantyRequest = WarrantyRequest::create([
                'product_id' => $validated['product_id'],
                'customer_id' => $validated['customer_id'],
                'issue_description' => $validated['issue_description'],
                'received_date' => $validated['received_date'] ?? now(),
            ]);

            // Cập nhật hoặc tạo tồn kho bảo hành
            $inventory = WarrantyInventory::firstOrNew([
                'product_id' => $validated['product_id'],
                'warehouse_id' => $validated['warehouse_id'],
            ]);
            $inventory->quantity = ($inventory->quantity ?? 0) + 1;
            $inventory->warranty_status = 'pending';
            $inventory->save();

            return response()->json([
                'message' => 'Warranty request received successfully',
                'request_id' => $warrantyRequest->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to receive warranty request.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getWarrantySent(Request $request)
    {
        try {
            // Lấy tham số từ query
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Xây dựng truy vấn
            $query = WarrantyRequest::select('id', 'product_id', 'supplier_id', 'sent_date', 'issue_description')
                ->with(['product' => function ($query) {
                    $query->select('id', 'title');
                }])
                ->whereNotNull('sent_date');

            // Lọc theo start_date và end_date
            if ($startDate) {
                $request->validate([
                    'start_date' => 'date_format:Y-m-d',
                ]);
                $query->where('sent_date', '>=', $startDate . ' 00:00:00');
            }

            if ($endDate) {
                $request->validate([
                    'end_date' => 'date_format:Y-m-d',
                ]);
                $query->where('sent_date', '<=', $endDate . ' 23:59:59');
            }

            // Kiểm tra start_date <= end_date
            if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
                return response()->json(['error' => 'Start date must be before end date.'], 400);
            }

            // Lấy danh sách yêu cầu
            $requests = $query->orderBy('sent_date', 'desc')->get();

            // Định dạng phản hồi
            $formattedRequests = $requests->map(function ($item) {
                return [
                    'request_id' => $item->id,
                    'product_id' => $item->product_id,
                    'title' => $item->product->title,
                    'supplier_id' => $item->supplier_id,
                    'sent_date' => $item->sent_date->toIso8601String(),
                    'issue_description' => $item->issue_description,
                ];
            });

            return response()->json($formattedRequests, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch warranty sent requests.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function sendWarranty(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'request_id' => 'required|integer|exists:warranty_requests,id',
                'supplier_id' => 'required|integer|exists:suppliers,id',
                'sent_date' => 'nullable|date_format:Y-m-d H:i:s',
            ]);

            // Tìm yêu cầu bảo hành
            $warrantyRequest = WarrantyRequest::findOrFail($validated['request_id']);

            // Kiểm tra xem yêu cầu đã được gửi chưa
            if ($warrantyRequest->sent_date) {
                return response()->json(['error' => 'This warranty request has already been sent.'], 400);
            }

            // Tìm bản ghi trong warranty_inventory
            $inventory = WarrantyInventory::where('product_id', $warrantyRequest->product_id)
                ->where('warranty_status', 'pending')
                ->first();

            if (!$inventory || $inventory->quantity <= 0) {
                return response()->json(['error' => 'No available items in warranty inventory to send.'], 400);
            }

            // Cập nhật yêu cầu bảo hành
            $warrantyRequest->update([
                'supplier_id' => $validated['supplier_id'],
                'sent_date' => $validated['sent_date'] ?? now(),
            ]);

            // Giảm số lượng trong warranty_inventory
            $inventory->quantity -= 1;
            if ($inventory->quantity <= 0) {
                $inventory->delete(); // Xóa bản ghi nếu số lượng về 0
            } else {
                $inventory->save();
            }

            return response()->json([
                'message' => 'Warranty request sent successfully',
                'request_id' => $warrantyRequest->id,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send warranty request.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getWarrantyReturned(Request $request)
    {
        try {
            // Lấy tham số từ query
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            // Xây dựng truy vấn
            $query = WarrantyRequest::select('id', 'product_id', 'customer_id', 'returned_date', 'resolution')
                ->with(['product' => function ($query) {
                    $query->select('id', 'title');
                }])
                ->whereNotNull('returned_date');

            // Lọc theo start_date và end_date
            if ($startDate) {
                $request->validate([
                    'start_date' => 'date_format:Y-m-d',
                ]);
                $query->where('returned_date', '>=', $startDate . ' 00:00:00');
            }

            if ($endDate) {
                $request->validate([
                    'end_date' => 'date_format:Y-m-d',
                ]);
                $query->where('returned_date', '<=', $endDate . ' 23:59:59');
            }

            // Kiểm tra start_date <= end_date
            if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
                return response()->json(['error' => 'Start date must be before end date.'], 400);
            }

            // Lấy danh sách yêu cầu
            $requests = $query->orderBy('returned_date', 'desc')->get();

            // Định dạng phản hồi
            $formattedRequests = $requests->map(function ($item) {
                return [
                    'request_id' => $item->id,
                    'product_id' => $item->product_id,
                    'title' => $item->product->title,
                    'customer_id' => $item->customer_id,
                    'returned_date' => $item->returned_date->toIso8601String(),
                    'resolution' => $item->resolution,
                ];
            });

            return response()->json($formattedRequests, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch warranty returned requests.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function returnWarranty(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'request_id' => 'required|integer|exists:warranty_requests,id',
                'resolution' => 'required|string',
                'returned_date' => 'nullable|date_format:Y-m-d H:i:s',
            ]);

            // Tìm yêu cầu bảo hành
            $warrantyRequest = WarrantyRequest::findOrFail($validated['request_id']);

            // Kiểm tra xem yêu cầu đã được nhận chưa
            if (!$warrantyRequest->received_date) {
                return response()->json(['error' => 'This warranty request has not been received yet.'], 400);
            }

            // Kiểm tra xem yêu cầu đã được trả chưa
            if ($warrantyRequest->returned_date) {
                return response()->json(['error' => 'This warranty request has already been returned.'], 400);
            }

            // Cập nhật yêu cầu bảo hành
            $warrantyRequest->update([
                'returned_date' => $validated['returned_date'] ?? now(),
                'resolution' => $validated['resolution'],
            ]);

            return response()->json([
                'message' => 'Warranty request returned successfully',
                'request_id' => $warrantyRequest->id,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to return warranty request.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function transferToSell(Request $request)
    {
        try {
            // Validate tham số
            $validated = $request->validate([
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'sell_warehouse_id' => 'required|integer|exists:warehouses,id|different:warehouse_id',
                'products' => 'required|array|min:1',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
            ]);

            // Bắt đầu transaction
            return DB::transaction(function () use ($validated) {
                // Tạo bản ghi trong transfers
                $transfer = Transfer::create([
                    'from_warehouse_id' => $validated['warehouse_id'],
                    'to_warehouse_id' => $validated['sell_warehouse_id'],
                    'type' => 'repair', // Chuyển từ kho bảo hành sang kho bán
                    'reason' => 'Transfer to sell after warranty',
                ]);

                foreach ($validated['products'] as $product) {
                    $productId = $product['product_id'];
                    $quantity = $product['quantity'];

                    // Kiểm tra số lượng trong warranty_inventory
                    $inventory = WarrantyInventory::where('warehouse_id', $validated['warehouse_id'])
                        ->where('product_id', $productId)
                        ->first();

                    if (!$inventory || $inventory->quantity < $quantity) {
                        throw new \Exception("Not enough quantity for product ID {$productId} in warranty inventory.");
                    }

                    // Giảm số lượng trong warranty_inventory
                    $inventory->quantity -= $quantity;
                    if ($inventory->quantity <= 0) {
                        $inventory->delete();
                    } else {
                        $inventory->save();
                    }

                    // Tăng số lượng trong products.quantity
                    $productRecord = Product::findOrFail($productId);
                    $productRecord->quantity += $quantity;
                    $productRecord->save();

                    // Lưu chi tiết chuyển kho
                    TransferDetail::create([
                        'transfer_id' => $transfer->id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ]);
                }

                return response()->json([
                    'message' => 'Transfer to sell warehouse successful',
                    'transfer_id' => $transfer->id,
                ], 200);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to transfer to sell warehouse.',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
