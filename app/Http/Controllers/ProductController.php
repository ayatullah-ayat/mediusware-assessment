<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $query = Product::latest();
        if($request->has('title') && $request->title != null){
            $query->where('title','like', '%' . $request->title . '%');
        }
        if($request->has('variant') && $request->variant != null){
            $query->whereHas('productVariants', function($query) use($request){
                $query->where('variant', $request->variant);
            });
        }
        if($request->has('price_from') && $request->price_from != null){
            $query->whereHas('productVariantPrice', function($query) use($request){
                $query->where('price', '>=', $request->price_from);
            });
        }
        if($request->has('date') && $request->date != null){
            $date = date('Y-m-d', strtotime($request->date));
            $query->where(function($query) use($date){
                $query->whereDate('created_at', $date);
            });
        }

        $products = $query->paginate(2);

        $variant_products = collect($this->getProductVariantsBaseOnVariants());
        
        return view('products.index', compact('products', 'variant_products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function create()
    {
        $variants = Variant::all();
        return view('products.create', compact('variants'));
    }

    public function store(Request $request)
    {
        if(!$request->title){
            throw new Exception('You Must Provide product title');
        }
        if(!$request->sku){
            throw new Exception('You Must Provide product Sku');
        }

        try {
            DB::beginTransaction();
            $product = Product::create([
                    'title'         => $request->title, 
                    'sku'           => $request->sku, 
                    'description'   => $request->description
                ]);

            $product_image = new ProductImage();
            if($request->hasFile('product_image'))
            {
                foreach($request->file('product_image') as $img)
                {
                    $file = $img;
                    $filename = time().'-'.uniqid().'.'.$file->getClientOriginalExtension();
                    $file->move(public_path('uploads/products'), $filename);
                    // save filename to database
                    $product_image->create([
                        'product_id'    => $product->id, 
                        'file_path'     => $filename]);
                }
            }

            $product_variant = new ProductVariant();
            foreach($request->product_variant as $variant)
            {
                foreach($variant['tags'] as $tag)
                {
                    $product_variant->create([
                        'variant'       =>  $tag, 
                        'variant_id'    =>  $variant['option'], 
                        'product_id'    =>  $product->id
                    ]);
                }
            }

            foreach($request->product_variant_prices as $product_variant_price){
                $pv_prices = new ProductVariantPrice();
                $attrs = explode("/", $product_variant_price['title']);

                $product_variant_ids= [];
                for( $i=0; $i<count($attrs)-1; $i++){
                    $product_variant_ids[] = ProductVariant::select('id')->where('variant', $attrs[$i])->latest()->first()->id;
                }

                $product_variant_sequence = ['one', 'two', 'three'];
                for( $i=1; $i<=count($product_variant_ids); $i++){
                    $pv_prices->{'product_variant_'.$product_variant_sequence[$i-1]} = $product_variant_ids[$i-1];
                }
                

                $pv_prices->price = $product_variant_price['price'];
                $pv_prices->stock = $product_variant_price['stock'];
                $pv_prices->product_id = $product->id;
                $pv_prices->save();
            }
            DB::commit();
            return response()->json([
                "message" => "Product Added Successfully!",
                "success" => true
            ]);
        } catch (Exception $e) 
        {
            DB::rollBack();
            return response()->json([
                "message" => $e->getMessage(),
                "success" => false
            ]);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function show($product)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        $variants = Variant::all();
        return view('products.edit', compact('variants'));
    }

    public function update(Request $request, Product $product)
    {
        // update
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        //
    }



    public function getProductVariantsBaseOnVariants(){
        $variants = Variant::get();
        $product_variants = [];

        foreach ($variants as $variant) {
            $variant_data = ProductVariant::where('variant_id', $variant->id)->groupBy('variant')->get();

            $product_variants[] = [
                'id' => $variant->id,
                'variant_title' => $variant->title,
                'variant_data' => $variant_data
            ];
        }
        return $product_variants;
    }
}
