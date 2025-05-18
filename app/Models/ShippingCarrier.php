<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCarrier extends Model
{
    protected $fillable = ['name', 'phone'];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}