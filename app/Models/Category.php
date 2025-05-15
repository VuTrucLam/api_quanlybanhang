<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'categories';
    protected $fillable = ['name', 'description'];
    protected $hidden = ['created_at', 'updated_at'];

    public function products()
    {
        return $this->hasMany(Product::class, 'cat_id');
    }
}