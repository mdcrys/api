<?php

namespace App\Http\Controllers;

use App\Models\Seccion;
use Illuminate\Http\Request;

class SeccionController extends Controller
{


    public function seccionesPorModulo($id)
{
    $secciones = \App\Models\Seccion::where('id_modulo', $id)->get(); // ✅ Columna correcta según tu modelo y DB


    return response()->json([
        'success' => true,
        'secciones' => $secciones
    ]);
}

    // Método para listar secciones por módulo
    public function index(Request $request)
    {
        // Validar que venga el id_modulo
        $request->validate([
            'id_modulo' => 'required|integer|exists:modulos,id',
        ]);

        $idModulo = $request->input('id_modulo');

        // Obtener las secciones que pertenecen a ese módulo
        $secciones = Seccion::where('modulo_id', $idModulo)->get();

        // Retornar en formato JSON
        return response()->json([
            'success' => true,
            'secciones' => $secciones,
        ]);
    }

    // Mostrar una sección por ID
    public function show($id)
    {
        $seccion = Seccion::with('modulo')->findOrFail($id);
        return response()->json($seccion);
    }

    // Crear una nueva sección
    public function store(Request $request)
    {
        $seccion = Seccion::create([
            'id_modulo' => $request->id_modulo,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'id_empresa' => $request->id_empresa,
            'estado' => 1
        ]);

        return response()->json([
            'success' => true,
            'seccion' => $seccion
        ]);
    }

    // Actualizar una sección existente
    public function update(Request $request, $id)
    {
        $seccion = Seccion::findOrFail($id);
        $seccion->update($request->only(['nombre', 'descripcion', 'estado']));
        return response()->json([
            'success' => true,
            'seccion' => $seccion
        ]);
    }

    // Eliminar lógicamente una sección
    public function destroy($id)
    {
        $seccion = Seccion::findOrFail($id);
        $seccion->delete(); // Soft delete
        return response()->json([
            'success' => true,
            'message' => 'Sección eliminada correctamente'
        ]);
    }
}
