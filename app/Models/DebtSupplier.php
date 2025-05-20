<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtSupplier extends Model
{
    protected $table = 'debts_supplier';
    protected $fillable = ['import_id', 'supplier_id', 'remaining_amount'];

    public function import()
    {
        return $this->belongsTo(Import::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}