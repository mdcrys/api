<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;

class ProyectoController extends Controller
{
    public function index(Request $request)
{
    $user = auth('api')->user(); // Obtiene el usuario autenticado

    $search = $request->get("search");

    $proyectos = \App\Models\Proyecto::with('empresa')
        ->where("id_empresa", $user->id_empresa) // FILTRA por empresa del usuario logueado
        ->when($search, function ($query, $search) {
            return $query->where("nombre", "like", "%" . $search . "%");
        })
        ->whereNull("deleted_at")
        ->orderBy("id_proyecto", "desc")
        ->paginate(25);

    return response()->json([
        "total" => $proyectos->total(),
        "proyectos" => $proyectos->map(function ($proyecto) {
            return [
                "id" => $proyecto->id_proyecto,
                "nombre" => $proyecto->nombre,
                "id_empresa" => $proyecto->id_empresa, // ✅ AÑADIDO AQUÍ
                "empresa" => $proyecto->empresa?->nombre_empresa ?? 'Sin empresa',
                "estado" => $proyecto->estado ?? 1,
                "created_at" => $proyecto->created_at?->format("Y-m-d h:i A"),
            ];
        }),

    ]);
}



    public function store(Request $request)
{
    // Validar datos requeridos
    $request->validate([
        'nombre' => 'required|string|max:255',
        'id_empresa' => 'required|exists:empresa,id_empresa',
    ]);

    // Crear proyecto
    $proyecto = \App\Models\Proyecto::create([
        'nombre' => $request->input('nombre'),
        'id_empresa' => $request->input('id_empresa'),
        'estado' => $request->input('estado', 1),
    ]);

    return response()->json([
        "message" => 200,
        "proyecto" => [
            "id" => $proyecto->id_proyecto,
            "nombre" => $proyecto->nombre,
            "empresa" => $proyecto->empresa?->nombre_empresa,
            "estado" => $proyecto->estado,
            "created_at" => $proyecto->created_at?->format("Y-m-d h:i A")
        ]
    ]);
}




    public function update(Request $request, $id)
    {
        //dd($id);
        // Busca el proyecto por su ID
        $proyecto = \App\Models\Proyecto::findOrFail($id);

        // Validar que venga un nombre y que exista la empresa
        $request->validate([
            'nombre' => 'required|string|max:255',
            'id_empresa' => 'required|exists:empresa,id_empresa',
        ]);

        // Actualizar datos del proyecto
        $proyecto->update([
            'nombre' => $request->input('nombre'),
            'id_empresa' => $request->input('id_empresa'),
            'estado' => $request->input('estado', $proyecto->estado),
        ]);

        // Retornar respuesta con formato
        return response()->json([
            "message" => 200,
            "proyecto" => [
                "id" => $proyecto->id_proyecto,
                "nombre" => $proyecto->nombre,
                "id_empresa" => $proyecto->id_empresa, // ← aquí el ID
                "empresa" => $proyecto->empresa?->nombre_empresa ?? 'Sin empresa',
                "estado" => $proyecto->estado,
                "created_at" => $proyecto->created_at?->format("Y-m-d h:i A"),
            ]
        ]);
    }



    public function destroy($id)
    {
        $proyecto = \App\Models\Proyecto::findOrFail($id);

        // Eliminación lógica (soft delete)
        $proyecto->deleted_at = now();
        $proyecto->save();

        return response()->json([
            "message" => 200,
            "message_text" => "Proyecto eliminado correctamente"
        ]);
    }

}
