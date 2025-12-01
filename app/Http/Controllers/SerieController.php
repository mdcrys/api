<?php

namespace App\Http\Controllers;

use App\Models\Serie;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use App\Models\Indexacion;

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

    $proyecto = Proyecto::with(['parent.parent']) // 🔥 carga padres en cascada
        ->find($idSubSeccion);

    if (!$proyecto) {
        return response()->json(['error' => 'Proyecto no encontrado'], 404);
    }

    // Construir jerarquía ordenada
    $ruta = [];

    $actual = $proyecto;
    while ($actual) {
        array_unshift($ruta, [
            'id' => $actual->id_proyecto,
            'nombre' => $actual->nombre,
            'nivel' => $actual->nivel
        ]);
        $actual = $actual->parent;
    }
    /* dd([
        'proyecto_actual' => $proyecto,
        'jerarquia' => $ruta
    ]);*/

    return response()->json([
        'proyecto_actual' => $proyecto,
        'jerarquia' => $ruta
    ]);
}








public function DatosSerie(Request $request)
{
    $request->validate([
        'idSerie' => 'required|integer'
    ]);

    $idSerie = $request->idSerie;

    $serie = Serie::with(['empresa'])
                ->where('id_serie', $idSerie)
                ->first();

    if (!$serie) {
        return response()->json(['error' => 'Serie no encontrada'], 404);
    }

    $ruta = [];

    // =====================================
    // 1. CONSTRUIR JERARQUÍA DE PROYECTO
    // =====================================
    if ($serie->id_subseccion) {

        $proyecto = Proyecto::find($serie->id_subseccion);

        while ($proyecto) {

            array_unshift($ruta, [
                'tipo'   => 'proyecto',
                'id'     => $proyecto->id_proyecto,
                'nombre'=> $proyecto->nombre
            ]);

            if ($proyecto->padre_id) {
                $proyecto = Proyecto::find($proyecto->padre_id);
            } else {
                break;
            }
        }
    }

    // ==============================
    // 2. AGREGAR LA SERIE AL FINAL
    // ==============================
    $ruta[] = [
        'tipo'   => 'serie',
        'id'     => $serie->id_serie,
        'nombre'=> $serie->nombre
    ];

    return response()->json([
        'serie'     => $serie,
        'jerarquia'=> $ruta
    ]);
}





    // Crear nueva serie
   public function guardarSerie(Request $request)
{
    // Para verificar siempre primero:
    // dd($request->all());

    // 1. Crear la serie
    $serie = Serie::create([

        // RELACIONES
        'id_subseccion'        => $request->idSubseccion,
        'id_empresa'            => $request->id_empresa,

        // PRINCIPALES
        'nombre'                => $request->nombre,
        'descripcion'           => $request->descripcion,
        'origen'                => $request->origen,
        'acceso'                => $request->acceso,

        // PLAZOS
        'plazo_gestion'         => $request->plazoGestion ?? 0,
        'plazo_central'         => $request->plazoCentral ?? 0,
        'plazo_intermedio'      => $request->plazoIntermedio ?? 0,
        'plazo_historico'        => $request->plazoHistorico ?? 0,

        // OTROS
        'base_legal'             => $request->baseLegal,
        'disposicion_final'       => $request->disposicionFinal,
        'tecnica_seleccion'       => $request->tecnicaSeleccion,
        'parametros_indexados'    => json_encode($request->parametrosIndexados),

        'tiempo_conservacion'    => $request->tiempoConservacion ?? 0,
        'serie_activa'           => $request->serieActiva ?? 1,
        'prioridad'              => $request->prioridad,
        'observaciones_respuesta'=> $request->observacionesRespuesta,
        'revisado_digitado_por'  => $request->revisadoDigitadoPor,

        // ==========================
        // FICHA TÉCNICA
        // ==========================

        'numero_expediente'   => $request->fichaTecnica['numeroExpediente'] ?? null,
        'detalle_fisico'       => $request->fichaTecnica['detalleFisico'] ?? null,
        'plazo_conservacion'    => $request->fichaTecnica['plazoConservacion'] ?? null,
        'archivo_gestion'       => $request->fichaTecnica['archivoGestion'] ?? null,
        'archivo_central'       => $request->fichaTecnica['archivoCentral'] ?? null,
        'archivo_historico'      => $request->fichaTecnica['archivoHistorico'] ?? null,

        'criterios' => isset($request->fichaTecnica['criterios'])
                        ? json_encode($request->fichaTecnica['criterios'])
                        : null,

        'estado'   => 1
    ]);


    // 2. Buscar configuración base de indexación
    $indexacion = Indexacion::where('id_subseccion2', $request->idSubseccion)->first();

    if ($indexacion) {

        Indexacion::create([
            'id_serie'             => $serie->id_serie,
            'id_subseccion'        => $indexacion->id_subseccion,
            'id_subseccion2'       => $indexacion->id_subseccion2,
            'id_proyecto'          => $indexacion->id_proyecto,

            'descripcion_serie'    => $request->descripcion ?? null,
            'origen_documentacion' => $request->origen ?? null,
            'condiciones_acceso'   => $request->acceso ?? null,

            'campos_extra' => json_encode([]),
            'archivo_url'  => null,
            'estado'       => 1,
            'creado_en'    => now(),
            'updated_at'   => now(),
            'id_empresa'   => $request->id_empresa
        ]);
    }

    return response()->json([
        'message' => '✅ Serie creada correctamente',
        'serie' => $serie,
        'indexacion_encontrada' => $indexacion ? true : false
    ], 201);
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
    // Validamos los datos obligatorios
    $request->validate([
        'idPadre'    => 'required|integer',
        'id_empresa' => 'required|integer',
        'nombre'     => 'required|string|max:255',
    ]);

    try {
        // 1️⃣ Creamos la nueva sub-serie con todos los campos
        $subserie = Serie::create([
            'id_empresa'          => $request->id_empresa,
            'padre_id'            => $request->idPadre, // aquí se guarda el ID del padre
            'nombre'              => $request->nombre,
            'descripcion'         => $request->descripcion ?? null,
            'origen'              => $request->origen ?? null,
            'acceso'              => $request->acceso ?? null,

            // 📄 Plazos
            'plazo_gestion'       => $request->plazos['gestion'] ?? null,
            'plazo_central'       => $request->plazos['central'] ?? null,
            'plazo_intermedio'    => $request->plazos['intermedio'] ?? null,
            'plazo_historico'     => $request->plazos['historico'] ?? null,
            'base_legal'          => $request->plazos['baseLegal'] ?? null,
            'disposicion_final'   => $request->plazos['disposicionFinal'] ?? null,
            'tecnica_seleccion'   => $request->plazos['tecnicaSeleccion'] ?? null,

            // ⚙️ Parámetros indexados
            'parametros_indexados'=> json_encode($request->parametros ?? []),

            // 📝 Ficha Técnica
            'numero_expediente'   => $request->fichaTecnica['numeroExpediente'] ?? null,
            'detalle_fisico'      => $request->fichaTecnica['detalleFisico'] ?? null,
            'plazo_conservacion'  => $request->fichaTecnica['plazoConservacion'] ?? null,
            'archivo_gestion'     => $request->fichaTecnica['archivoGestion'] ?? null,
            'archivo_central'     => $request->fichaTecnica['archivoCentral'] ?? null,
            'archivo_historico'   => $request->fichaTecnica['archivoHistorico'] ?? null,
            'criterios'           => $request->fichaTecnica['criterios'] ?? [],

            'nivel'               => $request->nivel ?? 2,
            'estado'              => $request->estado ?? 1,
        ]);

        // 2️⃣ Crear registro en Indexacion si existe serie padre
        $indexacionPadre = Indexacion::where('id_serie', $request->idPadre)->first();

        if ($indexacionPadre) {
            Indexacion::create([
                'id_proyecto'         => $indexacionPadre->id_proyecto,
                'id_subseccion'       => $indexacionPadre->id_subseccion,
                'id_subseccion2'      => $indexacionPadre->id_subseccion2,
                'id_serie'            => $indexacionPadre->id_serie,
                'id_subserie'         => $subserie->id_serie,
                'descripcion_serie'   => $request->descripcion,
                'origen_documentacion'=> $request->origen,
                'condiciones_acceso'  => $request->acceso,
                'campos_extra'        => json_encode([]),
                'archivo_url'         => null,
                'estado'              => 1,
                'creado_en'           => now(),
                'updated_at'          => now(),
                'id_empresa'          => $request->id_empresa,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sub-Serie creada correctamente con toda la información',
            'data'    => $subserie
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al crear la Sub-Serie: ' . $e->getMessage()
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
