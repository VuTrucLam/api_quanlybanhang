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
    public function user()
    {
        return $this->belongsTo(User::class, 'customer_id'); // Liên kết customer_id với bảng users
    }
}