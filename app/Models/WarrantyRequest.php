<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyRequest extends Model
{
    protected $fillable = ['product_id', 'customer_id', 'received_date', 'issue_description'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}