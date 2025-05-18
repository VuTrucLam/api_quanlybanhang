<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['user_id', 'shipping_carrier_id', 'total_amount', 'sale_date'];

    public function user()
    {
        return $this->belongsTo(User::class); // Liên kết với User thay vì Customer
    }

    public function shippingCarrier()
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }
}