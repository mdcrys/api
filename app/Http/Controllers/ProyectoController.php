<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;
use App\Models\Indexacion;


class ProyectoController extends Controller
{
    public function index(Request $request)
    {
        $user = auth('api')->user(); // Obtiene el usuario autenticado

        $search = $request->get("search");

        $proyectos = \App\Models\Proyecto::with('empresa')
            ->where("id_empresa", $user->id_empresa) // FILTRA por empresa del usuario logueado
             ->whereNull("padre_id") // ✅ Solo proyectos que NO tengan padre
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



    // Método para traer datos de un proyecto
    public function DatosProyecto(Request $request)
    {
        $request->validate([
            'idProyecto' => 'required|integer', // Laravel espera integer
        ]);

        $id = (int) $request->idProyecto; // forzamos a integer

        $proyecto = Proyecto::find($id);

        if (!$proyecto) {
            return response()->json(['message' => 'Proyecto no encontrado'], 404);
        }

        return response()->json($proyecto);
    }



     // Para traer solo las subsecciones de un proyecto (opcional)
    public function listSubsecciones(Request $request)
    {
        $request->validate([
            'idProyecto' => 'required|integer'
        ]);

        $idProyecto = $request->input('idProyecto');

        $subsecciones = Proyecto::where('padre_id', $idProyecto)
            ->with('subsecciones') // recursivo
            ->get();

        return response()->json([
            'data' => $subsecciones
        ]);
    }


    // Guardar subsección (recursiva) y registro en indexaciones
public function guardarSubseccion(Request $request)
{
    // Validación
    $request->validate([
        'idProyecto' => 'required|integer|exists:proyecto,id_proyecto',
        'nombre' => 'required|string|max:255',
    ]);

    try {
        // Obtenemos el proyecto padre
        $proyectoPadre = Proyecto::findOrFail($request->idProyecto);

        // Creamos la subsección como un proyecto hijo
        $subseccion = Proyecto::create([
            'id_empresa' => $proyectoPadre->id_empresa, // heredamos la empresa
            'nombre' => $request->nombre,
            'padre_id' => $proyectoPadre->id_proyecto,
            'nivel' => $proyectoPadre->nivel + 1,
            'estado' => 1
        ]);

        // --- NUEVO REGISTRO EN INDEXACIONES ---
        Indexacion::create([
            'id_proyecto' => $subseccion->id_proyecto, // vinculamos al id de la subsección
            'campos_extra' => json_encode([]),       // inicializamos vacío
            'archivo_url' => null,                   // o '' si quieres
            'estado' => 1,
            'creado_en' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'subseccion' => $subseccion,
            'message' => 'Subsección e indexación creada correctamente'
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al crear la subsección y la indexación',
            'error' => $e->getMessage()
        ], 500);
    }
}




    // Método para traer datos de una subsección con sus subsecciones hijas
 public function DatosSubSeccion(Request $request)
{
    $idSubseccion = $request->input('idSubseccion'); // ⚠️ input del body
    if (!$idSubseccion) {
        return response()->json(['error' => 'No se recibió el idSubseccion'], 400);
    }

    $subseccion = Proyecto::with('subsecciones')
        ->where('id_proyecto', $idSubseccion)
        ->first();

    if (!$subseccion) {
        return response()->json(['error' => 'Subsección no encontrada'], 404);
    }

    return response()->json([
        'id' => $subseccion->id_proyecto,
        'nombre' => $subseccion->nombre,
        'padre_id' => $subseccion->padre_id,
        'nivel' => $subseccion->nivel,
        'subsecciones' => $subseccion->subsecciones
    ]);
}



public function listSubsecciones1(Request $request)
{
    $idSubSeccion = $request->query('idSubSeccion'); // ⚠️ coincide con query param
    if (!$idSubSeccion) {
        return response()->json(['error' => 'No se recibió el idSubSeccion'], 400);
    }

    $subseccion = Proyecto::with('subsecciones')
        ->where('id_proyecto', $idSubSeccion)
        ->first();

    if (!$subseccion) {
        return response()->json(['error' => 'Subsección no encontrada'], 404);
    }

    return response()->json([
        'id' => $subseccion->id_proyecto,
        'nombre' => $subseccion->nombre,
        'padre_id' => $subseccion->padre_id,
        'nivel' => $subseccion->nivel,
        'subsecciones' => $subseccion->subsecciones
    ]);
}


    public function guardarSubseccion1(Request $request)
    {
        // Validación de datos
        $request->validate([
            'nombre' => 'required|string|max:255',
            'idSubseccion' => 'required|integer|exists:proyecto,id_proyecto',
        ]);

        try {
            // Obtener el proyecto padre
            $proyectoPadre = Proyecto::findOrFail($request->idSubseccion);

            // Crear nueva subsección
            $subseccion = Proyecto::create([
                'nombre' => $request->nombre,
                'padre_id' => $proyectoPadre->id_proyecto, // vinculamos al padre
                'nivel' => $proyectoPadre->nivel + 1, // nivel según el padre
                'estado' => 1, // activo por defecto
                'id_empresa' => $proyectoPadre->id_empresa,
            ]);

            // Crear registro en Indexacion automáticamente
            Indexacion::create([
                'id_modulo' => $subseccion->id_proyecto, // vinculamos con la subsección creada
                'campos_extra' => json_encode([]),       // campos vacíos por defecto
                'archivo_url' => null,
                'estado' => 1,
                'creado_en' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SubSección creada correctamente y registrada en Indexacion',
                'data' => $subseccion
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la SubSección',
                'error' => $e->getMessage()
            ], 500);
        }
    }





         public function DatosSubSeccion1(Request $request)
    {
        $idSubseccion = $request->input('idSubseccion'); // ⚠️ input del body
        if (!$idSubseccion) {
            return response()->json(['error' => 'No se recibió el idSubseccion'], 400);
        }

        $subseccion = Proyecto::with('subsecciones')
            ->where('id_proyecto', $idSubseccion)
            ->first();

        if (!$subseccion) {
            return response()->json(['error' => 'Subsección no encontrada'], 404);
        }

        return response()->json([
            'id' => $subseccion->id_proyecto,
            'nombre' => $subseccion->nombre,
            'padre_id' => $subseccion->padre_id,
            'nivel' => $subseccion->nivel,
            'subsecciones' => $subseccion->subsecciones
        ]);
    }



    public function listSubsecciones2(Request $request)
    {
        $idSubSeccion = $request->query('idSubSeccion'); // ⚠️ coincide con query param
        if (!$idSubSeccion) {
            return response()->json(['error' => 'No se recibió el idSubSeccion'], 400);
        }

        $subseccion = Proyecto::with('subsecciones')
            ->where('id_proyecto', $idSubSeccion)
            ->first();

        if (!$subseccion) {
            return response()->json(['error' => 'Subsección no encontrada'], 404);
        }

        return response()->json([
            'id' => $subseccion->id_proyecto,
            'nombre' => $subseccion->nombre,
            'padre_id' => $subseccion->padre_id,
            'nivel' => $subseccion->nivel,
            'subsecciones' => $subseccion->subsecciones
        ]);
    }


      public function guardarSubseccion2(Request $request)
    {
        // Validación de datos
        $request->validate([
            'nombre' => 'required|string|max:255',
            'idSubseccion' => 'required|integer|exists:proyecto,id_proyecto',
        ]);

        try {
            // Obtener el proyecto padre
            $proyectoPadre = Proyecto::findOrFail($request->idSubseccion);

            // Crear nueva subsección
            $subseccion = Proyecto::create([
                'nombre' => $request->nombre,
                'padre_id' => $proyectoPadre->id_proyecto, // vinculamos al padre
                'nivel' => $proyectoPadre->nivel + 1, // nivel según el padre
                'estado' => 1, // activo por defecto
                'id_empresa' => $proyectoPadre->id_empresa,
            ]);

            // Crear registro en Indexacion automáticamente
            $indexacion = Indexacion::create([
                'id_modulo' => $subseccion->id_proyecto, // vinculamos con la subsección creada
                'campos_extra' => json_encode([]),       // campos vacíos por defecto
                'archivo_url' => null,
                'estado' => 1,
                'creado_en' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SubSección creada correctamente y registrada en Indexacion',
                'subseccion' => $subseccion,
                'indexacion' => $indexacion
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la SubSección',
                'error' => $e->getMessage()
            ], 500);
        }
    }



         public function DatosSubSeccionSerie(Request $request)
        {
           // dd($request->all());
            $idSubseccion = $request->input('idSubSeccion'); // ⚠️ input del body
            if (!$idSubseccion) {
                return response()->json(['error' => 'No se recibió el idSubseccion'], 400);
            }

            $subseccion = Proyecto::with('subsecciones')
                ->where('id_proyecto', $idSubseccion)
                ->first();

            if (!$subseccion) {
                return response()->json(['error' => 'Subsección no encontrada'], 404);
            }

            return response()->json([
                'id' => $subseccion->id_proyecto,
                'nombre' => $subseccion->nombre,
                'padre_id' => $subseccion->padre_id,
                'nivel' => $subseccion->nivel,
                'subsecciones' => $subseccion->subsecciones
            ]);
        }


        public function listSubseccionesSerie(Request $request)
    {
        $idSubSeccion = $request->query('idSubSeccion'); // ⚠️ coincide con query param
        if (!$idSubSeccion) {
            return response()->json(['error' => 'No se recibió el idSubSeccion'], 400);
        }

        $subseccion = Proyecto::with('subsecciones')
            ->where('id_proyecto', $idSubSeccion)
            ->first();

        if (!$subseccion) {
            return response()->json(['error' => 'Subsección no encontrada'], 404);
        }

        return response()->json([
            'id' => $subseccion->id_proyecto,
            'nombre' => $subseccion->nombre,
            'padre_id' => $subseccion->padre_id,
            'nivel' => $subseccion->nivel,
            'subsecciones' => $subseccion->subsecciones
        ]);
    }

     

}
