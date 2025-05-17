<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyInventory extends Model
{
    protected $table = 'warranty_inventory';
    protected $fillable = ['product_id', 'warehouse_id', 'quantity', 'warranty_status'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}