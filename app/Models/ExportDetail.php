<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportDetail extends Model
{
    protected $fillable = ['export_id', 'product_id', 'quantity'];

    public function export()
    {
        return $this->belongsTo(Export::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}