<?php

namespace App\Http\Controllers\Proforma;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Proforma\Proforma;
use App\Http\Controllers\Controller;

class CalendarProformaController extends Controller
{
    public function cronograma(Request $request) {
        if(!auth('api')->user()->can('cronograma')){
            return response()->json([
                "message" => 'THIS ACTION IS UNAUTHORIZED',
            ],403);
        }
        $search_client = $request->search_client;
        $categorie_id = $request->categorie_id;
        $segment_client_id = $request->segment_client_id;
        $status_pay = $request->status_pay;
        $user = auth('api')->user();
        $contracts = Proforma::where("state_proforma",2)
                                ->filterCronograma($search_client,$categorie_id,$segment_client_id,$status_pay,$user)
                                ->whereYear("created_at",date("Y"))
                                ->orderBy("id","desc")
                                ->get();

        return response()->json([
            "contracts" => $contracts->map(function($contract) {
                $proforma_deliverie = $contract->proforma_deliverie;
                return  [
                    "title" => '#'.$contract->id .' ('.$proforma_deliverie->sucursal_deliverie->name.')',
                    "start" => Carbon::parse($proforma_deliverie->date_entrega)->format("Y-m-d")."T00:00:00",
                    "className" => $proforma_deliverie->sucursal_deliverie->color,
                    "contract_id" => $contract->id,
                ];
            })
        ]);
    }
}
