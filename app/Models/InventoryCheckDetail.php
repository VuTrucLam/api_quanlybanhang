<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCheckDetail extends Model
{
    protected $fillable = ['inventory_check_id', 'product_id', 'actual_quantity'];

    public function inventoryCheck()
    {
        return $this->belongsTo(InventoryCheck::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}