<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierDebtPayment extends Model
{
    protected $table = 'supplier_debt_payments';
    protected $fillable = ['debt_id', 'amount', 'payment_date'];

    public function debt()
    {
        return $this->belongsTo(DebtSupplier::class);
    }
}