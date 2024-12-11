<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductMaterial;
use App\Models\Warehouse;

class WarehouseController extends Controller
{
    public $Warehouses;
    public $Materials;

    public function index()
    {
        return response()->json(['result' => $this->makeproducts()]);
    }

    /**
     * Kelgan surovga asosan omborxonadan mahsulot oladi va ayiradi
     * @param
     *      - material_id: olinadigan xomashyo id raqami
     *      - quantity:    olinadigan xomashyo soni
     * @return
     *      - quantity:    keraklicha olingangan keyin yana qancha kerakligi
     *      - result:      oldingan mahsulot parametrlari qaytadi
     *          - warehouse_id:  omborxonaga kiritilgan xomashyo raqami
     *          - material_name: xomashyo nomi
     *          - qty:           olingan xomashyo soni
     *          - price:         olingan xomashyo kiritilgan narxi
     */
    private function removalWarehouse($material_id, $quantity){
        $Warehouses = [];

        $is_removal = false;
        $is_ending = true;

        foreach ($this->Warehouses as $v){
            if ($v['material_id'] == $material_id and $is_removal == false){
                $is_ending = false;

                if ($v['remainder'] >= $quantity){
                    $v['remainder'] -= $quantity;
                    $is_removal = true;

                    $res = [
                        'warehouse_id'  => $v['id'],
                        'material_name' => $this->Materials[$material_id],
                        'qty'           => $quantity,
                        'price'         => $v['price']
                    ];

                    $quantity = 0;
                } elseif ($v['remainder'] > 0){
                    $res = [
                        'warehouse_id'  => $v['id'],
                        'material_name' => $this->Materials[$material_id],
                        'qty'           => $v['remainder'],
                        'price'         => $v['price']
                    ];

                    $quantity -= $v['remainder'];
                    $v['remainder'] = 0;
                    $is_removal = true;
                }

                if ($v['remainder'] != 0){
                    $Warehouses[] = $v;
                }
            } else {
                $Warehouses[] = $v;
            }
        }

        //agar omborxonada tugagan bo'lsa
        if ($is_ending){
            $res = [
                'warehouse_id'  =>  null,
                'material_name' => $this->Materials[$material_id],
                'qty'           => $quantity,
                'price'         => null
            ];
            $quantity = 0;
        }

        $this->Warehouses = $Warehouses;

        return ['result' => $res, 'quantity' => $quantity];
    }

    /**
     * mahsulotlarni tayyorlash yani kerakli mahsulotlar uchun 
     * qancha mahsulot kerak bulishi
     */
    private function makeproducts(){
        $this->Materials = Material::pluck('name', 'id')->toArray();
        $Products = Product::all();
        $this->Warehouses = Warehouse::all();

        $response = [];

        //har bir mahsulot alohida ko'rib chiqiladi
        foreach ($Products as $product){
            $result = [];
            $id = $product['id'];
            $product_qty = $product['quantity'];

            //mahsulotga kerakli xomashyolar
            $pmaterials = ProductMaterial::where('product_id', $id)->get();

            //har bir kerakli xomashy ombordan olinadi
            foreach ($pmaterials as $pmaterial){
                $material_id = $pmaterial['material_id'];
                $material_qty = $pmaterial['quantity'] * $product_qty;

                //omborxona tugaguncha yoki kerakligicha olinadi
                while($material_qty != 0){
                    $take = $this->removalWarehouse($material_id, $material_qty);
                    $result[] = $take['result'];
                    $material_qty = $take['quantity'];
                }
            }

            $response[] = [
                'product_name'      => $product['name'],
                'product_qty'       => $product['quantity'],
                'product_materials' => $result
            ];
        }

        return $response;
    }

}
