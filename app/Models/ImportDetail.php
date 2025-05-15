<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportDetail extends Model
{
    protected $fillable = ['import_id', 'product_id', 'quantity', 'unit_price'];

    public function import()
    {
        return $this->belongsTo(Import::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}