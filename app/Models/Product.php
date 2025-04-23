<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'photo',
        'quantity',
        'description',
        'summary',
        'price',
        'cat_id',
    ];
}
