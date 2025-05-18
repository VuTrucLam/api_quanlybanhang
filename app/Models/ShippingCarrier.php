<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingCarrier extends Model
{
    protected $fillable = ['name', 'contact'];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}