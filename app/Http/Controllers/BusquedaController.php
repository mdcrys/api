<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Busqueda;


class BusquedaController extends Controller
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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }



    public function buscarDocumentos(Request $request)
{
    $texto = $request->busqueda;

    // Obtener todas las columnas de la tabla
    $columnas = Schema::getColumnListing('indexaciones');

    $query = Busqueda::query();

    foreach ($columnas as $columna) {

        // Si es numérico
        if (is_numeric($texto)) {
            $query->orWhere($columna, $texto);
        }

        // Buscar con LIKE en todos los campos
        $query->orWhere($columna, 'LIKE', "%$texto%");
    }

    // Ejecutar consulta
    $docs = $query->get();

    return response()->json($docs);
}


}
