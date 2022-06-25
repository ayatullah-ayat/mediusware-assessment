<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'title', 'sku', 'description'
    ];

    public function scopeFilter($query, array $filters){
        
        $query->when($filters['title'] ?? false, function($query, $title){
            $query->where(function($query) use($title) {
                $query->where('title', 'like', "%$title%");
            });
        });
        
        $query->when($filters['variant'] ?? false, function($query, $variant){
            $query->whereHas('productVariants', function($query) use($variant){
                $query->where('variant', $variant);
            });
        });
        
        // $query->when($filters['price_from'] ?? false, function($query, $price_from){
        //     $query->where(function($query) use($price_from){
        //         $query->where('product_variant_prices.price', '>=', $price_from)
        //             ->where('product_variant_prices.product_id', 'products.id');
        //     });
        // });

        // $query->when($filters['price_to'] ?? false, function($query, $price_to){
        //     $query->where(function($query) use($price_to){
        //         $query->where('product_variant_prices.price', '>=', $price_to)
        //             ->where('product_variant_prices.product_id', 'products.id');
        //     });
        // });

        // $query->when($filters['date'] ?? false, function($query, $date){
        //     $query->where(function($query)use($date){
        //         $query->where('products.created_at', $date);
        //     });
        // });
    }

    public function productVariants(){
        return $this->hasMany(ProductVariant::class);
    }
    public function productVariantPrice(){
        return $this->hasMany(ProductVariantPrice::class);
    }


}
