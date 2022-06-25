<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariantPrice extends Model
{
    protected $guarded = [];
    public function productVariantByColor(){
        return $this->hasOne(ProductVariant::class, 'id','product_variant_one');
    }
    public function productVariantBySize(){
        return $this->hasOne(ProductVariant::class, 'id', 'product_variant_two');
    }
    public function productVariantByStyle(){
        return $this->hasOne(ProductVariant::class, 'id', 'product_variant_three');
    }
}
