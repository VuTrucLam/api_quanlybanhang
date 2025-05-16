<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    protected $fillable = ['warehouse_id'];

    public function details()
    {
        return $this->hasMany(ExportDetail::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}