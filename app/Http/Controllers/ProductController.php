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
        $product = Product::with(['prices','product_variants'])->find($product->id);
        $variants = Variant::all();
        return view('products.edit', compact('variants', 'product'));
    }

    public function update(Request $request, Product $product)
    {
        try{
            $p_id = $product->id;
            $product = Product::where('id', $product->id)->update(['title' => $request->title, 'sku' => $request->sku, 'description' =>$request->description]);

            //  if there is image
            if($request->hasFile('product_image')) {
                // remove previous image
                $this->removeImage($p_id);
                $product_image = new ProductImage();
                foreach($request->file('product_image') as $img){
                    $file = $img;
                    $filename = time().'-'.uniqid().'.'.$file->getClientOriginalExtension();
                    $file->move(public_path('uploads/products'), $filename);
                    // save filename to database
                    $product_image->create(['product_id' => $p_id, 'file_path' => $filename]);
                }
            }
            // same variant will be updated new will added 
            // deleted tags will be deleted
            $product_variant = new ProductVariant();
            foreach($request->product_variant as $variant){
                $variant = json_decode($variant);
                $product_variants = $product_variant->where('variant_id',$variant->option)->where('product_id', $p_id)->get();
                $num_tags = 0;
                $num_product_variants = count($product_variants);
                foreach($variant->tags as $index=>$tag){
                    $num_tags +=1;
                    if($num_product_variants >= $index+1){
                        $product_variants[$index]->update(['variant'=>$tag]);
                    }else{
                        $product_variant->create(['variant'=>$tag, 'variant_id'=>$variant->option, 'product_id'=>$p_id]);
                    }
                }
                // delete previous extra variants
                for($i=1; $i <= $num_product_variants - $num_tags; $i++ ){
                    $product_variants[$num_product_variants-$i]->delete();
                }
            }

            // same combination will be updated new will added 
            // deleted combination will be deleted
            $num_req_prices = 0;
            foreach($request->product_variant_prices as $index=>$price){
                $price = json_decode($price);
                $attrs = explode("/", $price->title);
                $product_variant_ids= [];
                for( $i=0; $i<count($attrs)-1; $i++){
                    $product_variant_ids[] = ProductVariant::select('id')->where('variant', $attrs[$i])->latest()->first()->id;
                }

                $new_pv_prices = new ProductVariantPrice();
                $pv_prices = ProductVariantPrice::where('product_id', $p_id)->get();
                $num_pv_prices = count($pv_prices);

                $num_req_prices+=1;

                if($num_pv_prices >= $index+1){
                    for( $i=1; $i<=count($product_variant_ids); $i++){
                        $pv_prices[$index]->{'product_variant_'.$i} = $product_variant_ids[$i-1];
                    }
                    $pv_prices[$index]->price = $price->price;
                    $pv_prices[$index]->stock = $price->stock;
                    $pv_prices[$index]->product_id = $p_id;
                    $pv_prices[$index]->save();
                }else{
                    for( $i=1; $i<=count($product_variant_ids); $i++){
                        $new_pv_prices->{'product_variant_'.$i} = $product_variant_ids[$i-1];
                    }
                    $new_pv_prices->price = $price->price;
                    $new_pv_prices->stock = $price->stock;
                    $new_pv_prices->product_id = $p_id;
                    $new_pv_prices->save();
                }
            }
            // delete previous extra combination
            for($i=1; $i <= $num_pv_prices - $num_req_prices; $i++ ){
                $pv_prices[$num_pv_prices-$i]->delete();
            }


        } catch (Exception $e) {
            return response($e->getMessage(), 422);
        }
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
