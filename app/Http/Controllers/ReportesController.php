<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;          // 👈 FALTABA ESTO
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class ReportesController extends Controller
{


    public function exportarPDF(Request $request)
{
    $tipoReporte = $request->tipoReporte;
    $empresaId   = $request->id_empresa;
    $usuarioId   = $request->usuario_id;

    if (!$tipoReporte) {
        return response()->json(['error' => 'tipoReporte requerido'], 400);
    }

    // ✔️ AHORA SÍ ENVIAMOS LOS 3 PARÁMETROS
    $data = $this->getDataPorTipo($tipoReporte, $empresaId, $usuarioId);

    $view = match($tipoReporte) {
        1 => 'reportes.tabla',
        2 => 'reportes.cuadro',
        3 => 'reportes.inventario',
        default => 'reportes.pdf',
    };

    $pdf = PDF::loadView($view, compact('data'));

    return $pdf->download('reporte.pdf');
}



    /**
     * Generar Excel
     */
public function exportarExcel(Request $request)
{
    $tipoReporte = $request->tipoReporte;
    $empresaId   = $request->id_empresa;
    $usuarioId   = $request->usuario_id;

    if (!$tipoReporte) {
        return response()->json(['error' => 'tipoReporte requerido'], 400);
    }

    // Obtener los datos según el tipo
    $data = $this->getDataPorTipo($tipoReporte, $empresaId, $usuarioId);

    // Elegir el export según el tipo de reporte
    if ($tipoReporte == 1) {
        return Excel::download(
            new \App\Exports\GenericExport($empresaId, $usuarioId, $data, $tipoReporte),
            'cuadro_clasificacion.xlsx'
        );
    }

    if ($tipoReporte == 2) {
    return Excel::download(
        new \App\Exports\TablaPlazosExport($data['empresa'], $data['data']['indexaciones']),
        'tabla_plazos.xlsx'
    );
}


    // Si el tipo no está disponible
    return response()->json([
        'error' => 'Tipo de reporte no disponible para Excel'
    ], 400);
}





    /**
     * Obtener datos para cada tipo de reporte
     */
private function getDataPorTipo($tipo, $empresaId, $usuarioId)
{
    // ============================================
    // 🔥 CONSULTA SOLO EMPRESA
    // ============================================
    $empresa = DB::table('empresa')
        ->where('id_empresa', $empresaId)
        ->select('nombre_empresa', 'ruc_empresa', 'correo', 'telefono', 'direccion')
        ->first();

    // ============================================
    // 🔥 CONSULTA SOLO USUARIO
    // ============================================
    $usuario = DB::table('users')
        ->where('id', $usuarioId)
        ->select('name as usuario_nombre', 'email as usuario_email')
        ->first();

    // ============================================
    // 🔥 DATA SEGÚN TIPO
    // ============================================
    switch ($tipo) {

           case 1:
              $data = DB::table('indexaciones as i')
            // Proyecto principal
            ->leftJoin('proyecto as p1', 'p1.id_proyecto', '=', 'i.id_proyecto')        

            // Subsección 1
            ->leftJoin('proyecto as p2', 'p2.id_proyecto', '=', 'i.id_subseccion')      

            // Subsección 2
            ->leftJoin('proyecto as p3', 'p3.id_proyecto', '=', 'i.id_subseccion2')     

            // Serie principal
            ->leftJoin('serie as s1', 's1.id_serie', '=', 'i.id_serie')

            // Subserie
            ->leftJoin('serie as s2', 's2.id_serie', '=', 'i.id_subserie')

            ->where('i.id_empresa', $empresaId)
            ->select(
                'i.*',

                // Nombres de Proyecto/Subsecciones
                'p1.nombre as proyecto_nombre',
                'p2.nombre as subseccion_nombre',
                'p3.nombre as subseccion2_nombre',

                // Nombres de Serie / Subserie
                's1.nombre as serie_nombre',
                's2.nombre as subserie_nombre'
            )
            ->orderBy('i.creado_en', 'desc')
            ->get();



        break;

        case 2:
    // Construcción de la consulta con LEFT JOINs para obtener los nombres
    $indexaciones = DB::table('indexaciones as i')

        // Proyectos (Sección, Subsección 1, Subsección 2)
        ->leftJoin('proyecto as p1', 'p1.id_proyecto', '=', 'i.id_proyecto')      // Proyecto principal
        ->leftJoin('proyecto as p2', 'p2.id_proyecto', '=', 'i.id_subseccion')    // Subsección 1
        ->leftJoin('proyecto as p3', 'p3.id_proyecto', '=', 'i.id_subseccion2')   // Subsección 2

        // Series (Serie principal y Subserie)
        ->leftJoin('serie as s1', 's1.id_serie', '=', 'i.id_serie')               // Serie principal
        ->leftJoin('serie as s2', 's2.id_serie', '=', 'i.id_subserie')            // Subserie

        ->where('p1.id_empresa', $empresaId)                                      // Filtro basado en el proyecto principal
        ->select(
            'i.*',                                                                 // Todos los campos de indexaciones
            
            // Nombres de Proyecto/Subsecciones
            'p1.nombre as proyecto_nombre',
            'p2.nombre as subseccion_nombre',
            'p3.nombre as subseccion2_nombre',

            // Nombres de Serie / Subserie
            's1.nombre as serie_nombre',
            's2.nombre as subserie_nombre'
        )
        ->get();

    // Traer los datos de la empresa
    $empresa = \App\Models\Empresa::find($empresaId);

    // Retornar ambos
    $data = [
        'empresa' => $empresa,
        'indexaciones' => $indexaciones
    ];
    break;


       case 3:
        $data = DB::table('indexaciones as i')
            ->join('proyecto as p', 'p.id_proyecto', '=', 'i.id_proyecto')
            ->where('p.id_empresa', $empresaId)
            ->select('i.id_indexacion') // para probar
            ->get();

        dd($empresaId, $usuarioId, $data);
        break;



        default:
            $data = collect([]);
    }

    // ============================================
    // 🔥 RETORNAR EMPRESA + USUARIO + DATA
    // ============================================
    return [
        'empresa' => $empresa,
        'usuario' => $usuario,
        'data' => $data
    ];
}


}
