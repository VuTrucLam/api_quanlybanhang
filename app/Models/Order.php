<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['user_id', 'shipping_carrier_id', 'shipping_address', 'total_amount', 'status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shippingCarrier()
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }
}