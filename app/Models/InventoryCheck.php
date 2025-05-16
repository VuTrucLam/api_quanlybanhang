<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCheck extends Model
{
    protected $fillable = ['warehouse_id'];

    public function details()
    {
        return $this->hasMany(InventoryCheckDetail::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}