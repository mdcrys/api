<?php

namespace App\Http\Controllers;

use App\Models\Modulo;
use App\Models\Seccion;
use Illuminate\Http\Request;

class ModuloController extends Controller
{
   public function index(Request $request)
{
    $id_proyecto = $request->query('id_proyecto');
    $search = $request->query('search');

    $query = Modulo::query();

    // Filtrar solo por id_proyecto si viene
    if ($id_proyecto) {
        $query->where('id_proyecto', $id_proyecto);
    }

    // Filtro opcional por nombre
    if ($search) {
        $query->where('nombre', 'like', '%' . $search . '%');
    }

    $modulos = $query->orderBy('id', 'desc')->paginate(25);

    return response()->json([
        'total' => $modulos->total(),
        'modulos' => $modulos->map(function ($modulo) {
            return [
                'id' => $modulo->id,
                'nombre' => $modulo->nombre,
                'descripcion' => $modulo->descripcion,
                'estado' => $modulo->estado ?? 1,
                'id_proyecto' => $modulo->id_proyecto,
                'created_at' => $modulo->created_at?->format('Y-m-d h:i A'),
            ];
        }),
    ]);
}





 public function store(Request $request)
{
    // Validar datos de entrada
    $request->validate([
        'nombre' => 'required|string|max:100',
        'descripcion' => 'nullable|string',
        'id_proyecto' => 'required|exists:proyecto,id_proyecto',
    ]);

    // Crear módulo asociado al proyecto
    $modulo = Modulo::create([
        'nombre' => $request->nombre,
        'descripcion' => $request->descripcion,
        'id_proyecto' => $request->id_proyecto,
        'estado' => 1,
    ]);

    // Ya no se crean secciones automáticamente aquí

    // Retornar respuesta solo con el módulo
    return response()->json([
        'success' => true,
        'message' => 'Módulo creado correctamente',
        'modulo' => $modulo  // sin .load('secciones') si no vas a usarlas
    ]);
}





public function update(Request $request, $id)
{
   // dd($request);
    // Validar datos
    $request->validate([
        'nombre' => 'required|string|max:100',
        'descripcion' => 'nullable|string',
        'estado' => 'required|integer|in:0,1',
        
    ]);

    // Buscar módulo por id
    $modulo = Modulo::find($id);

    if (!$modulo) {
        return response()->json([
            'success' => false,
            'message' => 'Módulo no encontrado'
        ], 404);
    }

    // Actualizar campos
    $modulo->nombre = $request->nombre;
    $modulo->descripcion = $request->descripcion;
    $modulo->estado = $request->estado;
    $modulo->id_proyecto = $request->id_proyecto; // <-- asignar id_proyecto
    $modulo->save();

    return response()->json([
        'success' => true,
        'message' => 'Módulo actualizado correctamente',
        'modulo' => $modulo->load('secciones') // puedes quitar esto si ya no quieres secciones
    ]);
}

public function destroy($id)
{
    $modulo = Modulo::find($id);

    if (!$modulo) {
        return response()->json([
            'success' => false,
            'message' => 'Módulo no encontrado',
        ], 404);
    }

    // Opcional: eliminar también secciones relacionadas
    $modulo->secciones()->delete();

    $modulo->delete();

    return response()->json([
        'success' => true,
        'message' => 'Módulo eliminado correctamente',
    ]);
}

}
