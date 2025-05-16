<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = ['account_id', 'type', 'amount', 'category_id'];
}