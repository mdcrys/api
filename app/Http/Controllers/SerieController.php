<?php

namespace App\Http\Controllers;

use App\Models\Serie;
use App\Models\Proyecto;
use Illuminate\Http\Request;

class SerieController extends Controller
{
    // Mostrar todas las series
    public function listSerie(Request $request)
{
    $idSubSeccion = $request->query('idSubSeccion'); // obtenemos parámetro opcional
    $search = $request->query('search');

    $query = Serie::with(['padre', 'hijos', 'empresa']); // quitamos 'proyecto' porque tu tabla Serie no tiene id_proyecto

    if ($idSubSeccion) {
        $query->where('id_subseccion', $idSubSeccion);
    }

    if ($search) {
        $query->where('nombre', 'like', "%$search%");
    }

    $series = $query->orderBy('id_serie', 'desc')->paginate(10); // paginación opcional

    return response()->json($series);
}






public function DatosSubseccionNombre(Request $request)
{
    $request->validate([
        'idSubSeccion' => 'required|integer',
    ]);

    $idSubSeccion = $request->idSubSeccion;

    $proyecto = Proyecto::with(['subsecciones', 'empresa', 'modulos'])
        ->find($idSubSeccion);

    if (!$proyecto) {
        return response()->json(['error' => 'Proyecto no encontrado'], 404);
    }

    return response()->json($proyecto);
}







 public function DatosSerie(Request $request)
{
    $idSerie = $request->input('idSerie');

    // Buscar la serie con sus relaciones
    $serie = Serie::with(['padre', 'hijos', 'empresa', 'proyecto'])
                  ->where('id_serie', $idSerie)
                  ->first();

    return response()->json($serie);
}




    // Crear nueva serie
 public function guardarSerie(Request $request)
{
    // Validación de los campos requeridos
 

    try {
        // Crear la serie
        $serie = Serie::create([
            'id_subseccion' => $request->idSubseccion,
            'id_empresa'    => $request->id_empresa,
            'padre_id'      => $request->padre_id ?? null,
            'nombre'        => $request->nombre,
            'descripcion'   => $request->descripcion ?? null,
            'nivel'         => $request->nivel ?? 2,
            'estado'        => $request->estado ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serie creada correctamente',
            'data'    => $serie
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al crear la Serie: ' . $e->getMessage()
        ], 500);
    }
}
    // Mostrar una serie específica
    public function show($id)
    {
        $serie = Serie::with(['padre', 'hijos', 'proyecto', 'empresa'])->findOrFail($id);
        return response()->json($serie);
    }

    // Actualizar una serie
    public function update(Request $request, $id)
    {
        $serie = Serie::findOrFail($id);

        $validated = $request->validate([
            'id_proyecto' => 'sometimes|integer',
            'id_empresa' => 'sometimes|integer',
            'padre_id' => 'nullable|integer',
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'nivel' => 'nullable|integer',
            'estado' => 'nullable|integer',
        ]);

        $serie->update($validated);

        return response()->json($serie);
    }

    // Eliminar serie
    public function destroy($id)
    {
        $serie = Serie::findOrFail($id);
        $serie->delete(); // soft delete si lo configuraste
        return response()->json(['message' => 'Serie eliminada']);
    }



       public function listSubSerie(Request $request)
    {
        $idSubSeccion = $request->query('idSubSeccion'); // obtenemos parámetro opcional
        $search = $request->query('search');

        $query = Serie::with(['padre', 'hijos', 'empresa']); // quitamos 'proyecto' porque tu tabla Serie no tiene id_proyecto

        if ($idSubSeccion) {
            $query->where('id_subseccion', $idSubSeccion);
        }

        if ($search) {
            $query->where('nombre', 'like', "%$search%");
        }

        $series = $query->orderBy('id_serie', 'desc')->paginate(10); // paginación opcional

        return response()->json($series);
    }



     public function guardarSubSerie(Request $request)
{
    $request->validate([
        'idSerie'    => 'required|integer',
        'id_empresa' => 'required|integer',
        'nombre'     => 'required|string|max:255',
    ]);

    try {
        $serie = Serie::create([
            'id_empresa'  => $request->id_empresa,
            'padre_id'    => $request->idSerie, // 👉 padre
            'nombre'      => $request->nombre,
            'descripcion' => $request->descripcion ?? null,
            'nivel'       => $request->nivel ?? 2,
            'estado'      => $request->estado ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serie creada correctamente',
            'data'    => $serie
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al crear la Serie: ' . $e->getMessage()
        ], 500);
    }
}

public function indexSubSerie(Request $request)
{
    // Validamos que llegue el ID de la serie padre
    $request->validate([
        'idSerie' => 'required|integer',
    ]);

    $idSerie = $request->query('idSerie');
    $search  = $request->query('search');
    $pageSize = 10;

    // Traer todas las subseries cuyo padre_id = idSerie
    $query = Serie::with(['padre', 'hijos', 'empresa'])
        ->where('padre_id', $idSerie);

    if (!empty($search)) {
        $query->where('nombre', 'like', "%$search%");
    }

    $series = $query->orderBy('id_serie', 'desc')->paginate($pageSize);

    return response()->json($series);
}


}
