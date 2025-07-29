<?php

namespace App\Http\Controllers\Product;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product\ProductWarehouse;

class ProductWarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize("create",ProductWarehouse::class);
        $product_warehouse = ProductWarehouse::create([
            "product_id" => $request->product_id,
            "unit_id" => $request->unit_id,
            "warehouse_id" => $request->warehouse_id,
            "stock" => $request->quantity,
        ]);

        return response()->json([
            "message" => 200,
            "product_warehouse" => [
                "id" => $product_warehouse->id,
                "unit" => $product_warehouse->unit,
                "warehouse" => $product_warehouse->warehouse,
                "quantity" => $product_warehouse->stock,
              ]
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize("update",ProductWarehouse::class);
        $product_warehouse = ProductWarehouse::findOrFail($id);

        $product_warehouse->update([
            // "product_id" => $request->product_id,
            "unit_id" => $request->unit_id,
            "warehouse_id" => $request->warehouse_id,
            "stock" => $request->quantity,
        ]);

        return response()->json([
            "message" => 200,
            "product_warehouse" => [
                "id" => $product_warehouse->id,
                "unit" => $product_warehouse->unit,
                "warehouse" => $product_warehouse->warehouse,
                "quantity" => $product_warehouse->stock,
              ]
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize("delete",ProductWarehouse::class);
        $product_warehouse = ProductWarehouse::findOrFail($id);
        $product_warehouse->delete();
        return response()->json([
            "message" => 200
        ]);
    }
}
