<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyRequest extends Model
{
    protected $fillable = ['product_id', 'customer_id', 'received_date', 'supplier_id', 'sent_date', 'returned_date', 'resolution', 'issue_description'];

    protected $casts = [
        'received_date' => 'datetime',
        'sent_date' => 'datetime',
        'returned_date' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'customer_id'); // Liên kết customer_id với bảng users
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}