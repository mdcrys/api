<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Indexacion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcesarOcrJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\Proyecto;
use App\Models\Serie;

use setasign\Fpdf\Fpdf;

use ZipArchive;
use Smalot\PdfParser\Parser; // O la librería que uses para PDF
use setasign\Fpdi\Fpdi; // Para manipular PDFs página a página
use App\Jobs\ProcesarPdfJob;  // Job que vamos a crear

class IndexacionController extends Controller
{

    
    // Carpeta temporal para PDFs subidos
    private $tmpPath = 'tmp_uploads';
public function ocr(Request $request)
{
    $request->validate([
        'modulo_id' => 'required|integer',
        'archivos' => 'required|array',
        'archivos.*' => 'file|mimes:pdf',
    ]);

    $moduloId = $request->modulo_id;

    $baseDir = base_path('public/storage/public');
    $carpeta = $baseDir . DIRECTORY_SEPARATOR . $moduloId;

    if (!file_exists($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    $archivosProcesados = [];
    $erroresOCR = [];

    $archivosExistentes = glob($carpeta . DIRECTORY_SEPARATOR . "*_MODULO{$moduloId}_*.pdf");
    $contadorGlobal = count($archivosExistentes) > 0 ? count($archivosExistentes) + 1 : 1;

    foreach ($request->file('archivos') as $archivo) {
        $nombreOriginal = $archivo->getClientOriginalName();
        $info = pathinfo($nombreOriginal);
        $nombreBase = $info['filename'];

        $nuevoNombre = "{$nombreBase}_MODULO{$moduloId}_{$contadorGlobal}.pdf";
        $rutaArchivo = $carpeta . DIRECTORY_SEPARATOR . $nuevoNombre;

        $archivo->move($carpeta, $nuevoNombre);
        Log::info("Archivo guardado en: {$rutaArchivo}");

        if (!file_exists($rutaArchivo)) {
            Log::error("Archivo NO encontrado: {$rutaArchivo}");
            $erroresOCR[] = "{$nuevoNombre} no encontrado en el servidor.";
            $contadorGlobal++;
            continue;
        }

        $rutaOCR = $carpeta . DIRECTORY_SEPARATOR . "{$nombreBase}_MODULO{$moduloId}_{$contadorGlobal}_OCR.pdf";

        // Mejorar imagen y hacer OCR
        $command = "ocrmypdf --force-ocr --clean --clean-final --remove-background --deskew "
                   . escapeshellarg($rutaArchivo) . " "
                   . escapeshellarg($rutaOCR) . " 2>&1";

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        $joinedOutput = implode("\n", $output);

        if ($returnVar !== 0) {
            if (strpos($joinedOutput, 'DigitalSignatureError') !== false) {
                $erroresOCR[] = "{$nuevoNombre} tiene firma digital. No se pudo procesar OCR.";
                Log::warning("PDF con firma digital omitido: {$rutaArchivo}");
            } else {
                Log::error("Error ejecutando OCRmyPDF para: {$rutaArchivo}");
                Log::error("Salida OCRmyPDF: " . $joinedOutput);
                $erroresOCR[] = "Error al procesar {$nuevoNombre}";
            }
            $contadorGlobal++;
            continue;
        }

        // Reemplazar original con PDF OCR
        if (!rename($rutaOCR, $rutaArchivo)) {
            Log::error("No se pudo reemplazar el archivo original con OCR: {$rutaArchivo}");
            $erroresOCR[] = "No se pudo reemplazar el archivo original con OCR para {$nuevoNombre}";
            $contadorGlobal++;
            continue;
        }

        // Extraer texto con pdftotext
        $rutaTxt = $carpeta . DIRECTORY_SEPARATOR . "{$nombreBase}_MODULO{$moduloId}_{$contadorGlobal}.txt";
        $outputTxt = [];
        $returnVarTxt = 0;
        $commandTxt = "pdftotext " . escapeshellarg($rutaArchivo) . " -";
        exec($commandTxt, $outputTxt, $returnVarTxt);

        Log::info("Salida pdftotext para {$nuevoNombre}: " . implode("\n", $outputTxt));
        Log::info("Código de retorno pdftotext: " . $returnVarTxt);

        if ($returnVarTxt === 0 && count($outputTxt) > 0) {
            $textoExtraido = implode("\n", $outputTxt);
            file_put_contents($rutaTxt, $textoExtraido);
            Log::info("Archivo TXT creado: {$rutaTxt}");
        } else {
            Log::error("Error extrayendo texto con pdftotext de: {$rutaArchivo}");
            file_put_contents($rutaTxt, "Error extrayendo texto OCR para {$nuevoNombre}");
            Log::error("Archivo TXT NO se pudo crear o está vacío: {$rutaTxt}");
        }

        $rutaRelativa = "api/pdf/storage/public/{$moduloId}/{$nuevoNombre}";
        $url = url($rutaRelativa);

        $archivosProcesados[] = [
            'nombre' => $nuevoNombre,
            'ruta_relativa' => $rutaRelativa,
            'url' => $url
        ];

        $contadorGlobal++;
    }

    return response()->json([
        'message' => 'Archivos guardados y procesados con OCR',
        'archivos' => $archivosProcesados,
        'errores_ocr' => $erroresOCR,
    ]);
}







public function store(Request $request)
{
    // 🧪 Depuración temporal: muestra lo que llega
    // dd($request->all());

    // ✅ Validaciones
    $request->validate([
        'idProyecto' => 'required|integer',
        'campos' => 'required|string',
        'archivos.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
    ]);

    // ✅ Variables del request
    $idProyecto = $request->input('idProyecto');
    $idModulo = $request->input('idModulo');
    $campos = json_decode($request->input('campos'), true);
    $textoOCR = $request->input('textoOCR'); // 👈 Usa el mismo nombre que en tu dd()

    if ($campos === null) {
        return response()->json(['error' => 'Campos JSON inválido'], 400);
    }

    // ✅ Guardar archivos
    $archivosGuardados = [];
    if ($request->hasFile('archivos')) {
        foreach ($request->file('archivos') as $archivo) {
            if ($archivo->isValid()) {
                $carpetaDestino = 'public/documentos';
                $nombreBase = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $archivo->getClientOriginalExtension();
                $nombreArchivo = $nombreBase . '_' . time() . '.' . $extension;

                $ruta = $archivo->storeAs($carpetaDestino, $nombreArchivo);
                $archivosGuardados[] = $ruta;
            }
        }
    }

    // ✅ Crear el registro de indexación en base de datos
    $indexacion = Indexacion::create([
        'id_proyecto' => $idProyecto,
        'id_modulo' => $idModulo,
        'campos_extra' => $campos,
        'archivo_url' => json_encode($archivosGuardados),
        'texto_ocr' => $textoOCR, // 👈 Guardamos aquí el texto OCR
        'estado' => 1,
        'creado_en' => now(),
        'updated_at' => now(),
    ]);

    // ✅ Retornar respuesta JSON
    return response()->json([
        'mensaje' => 'Indexación guardada correctamente',
        'indexacion' => $indexacion,
    ]);
}




 public function index(Request $request)
    {
        $moduloId = $request->query('modulo_id');

        if (!$moduloId) {
            return response()->json([
                'error' => 'Se requiere el parámetro modulo_id'
            ], 400);
        }

        // Obtener las indexaciones filtradas por modulo_id
        $indexaciones = Indexacion::where('id_modulo', $moduloId)
            ->orderBy('creado_en', 'desc')
            ->get();

        return response()->json([
            'indexaciones' => $indexaciones
        ]);
    }


    // Método para traer siempre todos los campos_extra de la tabla indexaciones
   public function obtenerCamposExtra(Request $request)
    {
        $idProyecto = $request->input('idProyecto');

        if (!$idProyecto) {
            return response()->json(['error' => 'Falta idProyecto'], 400);
        }

        // Obtener todos los campos_extra de todas las indexaciones de ese proyecto
        $registros = Indexacion::where('id_proyecto', $idProyecto)
            ->pluck('campos_extra');

        // Aplanar los arrays y obtener títulos únicos
        $titulosUnicos = collect($registros)
            ->map(function ($campos) {
                if (is_array($campos)) {
                    return $campos; // ya está decodificado
                }
                if (is_string($campos)) {
                    return json_decode($campos, true) ?: [];
                }
                return [];
            })
            ->flatten(1)
            ->unique('titulo')
            ->values();

        return response()->json([
            'campos_extra' => $titulosUnicos,
        ]);
    }





  public function buscarDocumento(Request $request)
{
    $idProyecto = $request->input('id_proyecto');
    $campoValor = $request->input('campo_valor');
    $page = $request->input('page', 1);
    $perPage = 10;

    $query = Indexacion::with([
        'proyecto.parent',
        'proyecto.subsecciones',
        'proyecto.empresa',
        'proyecto.modulos'
    ]);

    // Filtrar por id_proyecto
    if ($idProyecto) {
        $query->where('id_proyecto', $idProyecto);
    }

    // Si existe texto para buscar en campos_extra
    if ($campoValor) {
        $palabras = explode(' ', $campoValor);

        $query->where(function($q) use ($palabras) {
            foreach ($palabras as $palabra) {
                $palabra = trim($palabra);
                if ($palabra !== '') {
                    $q->orWhere('campos_extra', 'LIKE', '%"valor":"%' . $palabra . '%"%');
                }
            }
        });
    }

    // Paginación
    $resultados = $query->paginate($perPage, ['*'], 'page', $page);

    return response()->json([
        'documentos' => $resultados,
    ]);
}







public function iniciarProcesoPdf(Request $request) {
    $request->validate(['archivo_pdf' => 'required|file|mimes:pdf']);

    $jobId = (string) Str::uuid();  // <-- Definir jobId aquí

    $pdfFile = $request->file('archivo_pdf');

    $tempFolder = storage_path('app/temp');
    if (!file_exists($tempFolder)) {
        mkdir($tempFolder, 0777, true);
    }

    $filename = uniqid() . '.pdf';
    $fullPath = $tempFolder . DIRECTORY_SEPARATOR . $filename;

    $pdfFile->move($tempFolder, $filename); // mover archivo para que el job lo procese

    Cache::put("progreso_pdf_{$jobId}", [
        'estado' => 'iniciado',
        'total_paginas' => 0,
        'paginas_procesadas' => 0,
        'zip_path' => null,
        'error' => null,
    ], 3600);

    ProcesarPdfJob::dispatch($fullPath, $jobId);

    return response()->json(['jobId' => $jobId]);
}


public function progresoPdf($jobId) {
    $progreso = Cache::get("progreso_pdf_{$jobId}");
    if (!$progreso) {
        return response()->json(['error' => 'Trabajo no encontrado'], 404);
    }

    // Si está finalizado, crear URL pública para descarga
    if ($progreso['estado'] === 'finalizado' && isset($progreso['zip_path'])) {
        $zipFileName = basename($progreso['zip_path']);
        $progreso['zipUrl'] = url("/descargar_pdf/{$jobId}"); // esta es la ruta que descarga el zip
    } else {
        $progreso['zipUrl'] = null;
    }

    return response()->json($progreso);
}


public function descargarPdf($jobId)
{
    $filePath = storage_path("app/temp/paginas_separadas_{$jobId}.zip");
    if (!file_exists($filePath)) {
        abort(404, "Archivo no encontrado");
    }
    return response()->download($filePath);
}



public function DatosNombre(Request $request)
{
    $request->validate([
        'id' => 'required|integer',
    ]);

    $id = $request->input('id');

    // Buscar proyecto con relaciones
    $proyecto = Proyecto::with(['subsecciones', 'empresa', 'modulos'])
        ->find($id);

    if (!$proyecto) {
        return response()->json([
            'success' => false,
            'message' => 'Proyecto no encontrado',
        ], 404);
    }

    // Tomar el primer módulo asociado (si existe)
    $idModulo = $proyecto->modulos->first()->id_modulo ?? null;

    // ✅ Buscar registros en indexaciones usando id_proyecto
    $indexaciones = Indexacion::where('id_proyecto', $id)->get();

    return response()->json([
        'success'      => true,
        'data'         => $proyecto,
        'idModulo'     => $idModulo,
        'indexaciones' => $indexaciones, // agregamos los registros de la tabla indexaciones
    ]);
}





public function DatosNombreSerie(Request $request)
{
   // dd($request->all());
    $request->validate([
        'id' => 'required|integer',
    ]);

    $id = $request->input('id');

    // Buscar proyecto con relaciones
    $proyecto = Proyecto::with(['subsecciones', 'empresa', 'modulos'])
        ->find($id);

    if (!$proyecto) {
        return response()->json([
            'success' => false,
            'message' => 'Proyecto no encontrado',
        ], 404);
    }

    // Tomar el primer módulo asociado (si existe)
    $idModulo = $proyecto->modulos->first()->id_modulo ?? null;

    // ✅ Buscar registros en indexaciones usando id_proyecto
    $indexaciones = Indexacion::where('id_proyecto', $id)->get();

    return response()->json([
        'success'      => true,
        'data'         => $proyecto,
        'idModulo'     => $idModulo,
        'indexaciones' => $indexaciones, // agregamos los registros de la tabla indexaciones
    ]);
}






public function storeIndexacionSerie(Request $request)
{

    //dd($request->all());
    $request->validate([
        'idSerie'    => 'required|integer',
        'idSubSerie' => 'nullable',
        'id_empresa' => 'required|integer',
        'campos'     => 'required|string',
        'archivos.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
    ]);

    $idEmpresa  = (int) $request->id_empresa;
    $idSerie    = (int) $request->idSerie;
    $idSubSerie = $request->idSubSerie ? (int)$request->idSubSerie : null;

    $camposExtra = json_decode($request->campos, true);

    if (!$camposExtra) {
        return response()->json([
            'success' => false,
            'mensaje' => 'JSON inválido en campos'
        ], 400);
    }

    /** ================= BUSCAR INDEXACIÓN ================= */
    $query = Indexacion::where('id_serie', $idSerie)
        ->where('id_empresa', $idEmpresa);

    if ($idSubSerie !== null) {
        $query->where('id_subserie', $idSubSerie);
    } else {
        $query->whereNull('id_subserie');
    }

    $indexacionExistente = $query->first();

    if (!$indexacionExistente) {
        return response()->json([
            'success' => false,
            'mensaje' => 'La indexación no existe, no se puede actualizar'
        ], 404);
    }

    /** ================= MANEJO DEL PDF ================= */

    $archivosGuardados = [];

    $idIndexacion = $indexacionExistente->id_indexacion;
    $carpetaDestino = "public/documentos/{$idIndexacion}";

    // 👉 Si sube nuevo archivo, reemplazar el anterior
    if ($request->hasFile('archivos')) {

        // 🗑️ BORRAR ARCHIVO ANTERIOR
        $archivoAnterior = json_decode($indexacionExistente->archivo_url, true);

        if ($archivoAnterior && isset($archivoAnterior[0])) {
            $rutaAnterior = storage_path('app/' . $archivoAnterior[0]);
            if (file_exists($rutaAnterior)) {
                unlink($rutaAnterior);
            }
        }

        // ✅ GUARDAR NUEVO PDF (solo 1)
        foreach ($request->file('archivos') as $archivo) {
            if ($archivo->isValid()) {

                $nombre = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $archivo->getClientOriginalExtension();
                $nuevoNombre = $nombre . '_' . time() . '.' . $extension;

                $ruta = $archivo->storeAs($carpetaDestino, $nuevoNombre);
                $archivosGuardados[] = $ruta;

                break; // ⚡ SOLO UN ARCHIVO
            }
        }
    } else {
        // Si no sube archivo, se mantiene el anterior
        $archivosGuardados = json_decode($indexacionExistente->archivo_url, true) ?? [];
    }


    /** ================= DATOS A ACTUALIZAR ================= */
    $datos = [
        'nro_folios'              => $request->nroFolios,
        'nro_paginas_digitales'   => $request->nroPaginasDigitales,
        'folio_secuencial_inicio' => $request->folioSecuencialInicio,
        'folio_secuencial_fin'    => $request->folioSecuencialFin,
        'tipo_soporte'            => $request->tipoSoporte,
        'tipo_acceso'             => $request->tipoAcceso,
        'estado_conservacion'     => $request->estadoConservacion,
        'revisado_digitado_por'   => $request->revisadoDigitadoPor,
        'plazo_gestion'     => $request->plazoGestion,
    'plazo_central'     => $request->plazoCentral,
     'plazo_intermedio' => $request->plazoIntermedio,
     'plazo_historico'  => $request->plazoHistorico,

    'base_legal'        => $request->baseLegal,
        'disposicion_final'       => $request->disposicionFinal,
        'tecnica_seleccion'       => $request->tecnicaSeleccion,
        'nro_caja'                => $request->nroCaja,
        'nro_carpeta_fisica'      => $request->nroCarpetaFisica,
        'nro_tomo'                => $request->nroTomo,
        'nro_estanteria'          => $request->nroEstanteria,
        'nro_bandeja'             => $request->nroBandeja,
        'observaciones_respuesta' => $request->observacionesRespuesta,

        'fecha_apertura' => $request->fechaApertura,
        'fecha_cierre'   => $request->fechaCierre,

        'campos_extra' => json_encode($camposExtra),
        'archivo_url'  => json_encode($archivosGuardados),
        'estado'       => 1,
    ];

    /** ================= UPDATE REAL ================= */
    $indexacionExistente->update($datos);

    return response()->json([
        'success' => true,
        'mensaje' => 'Documento actualizado correctamente',
        'indexacion' => $indexacionExistente
    ]);
}










public function DatosIndexacionSerie(Request $request)
{
    // Para depurar
   //dd($request->all());

    $request->validate([
        'id' => 'required|integer',
    ]);

    $idSerie = $request->input('id');

    // Buscar la serie con sus relaciones
    $serie = Serie::with(['hijos', 'empresa', 'padre'])
        ->find($idSerie);

    if (!$serie) {
        return response()->json([
            'success' => false,
            'message' => 'Serie no encontrada',
        ], 404);
    }

    // Obtener indexaciones relacionadas con esta serie
    $indexaciones = Indexacion::where('id_serie', $idSerie)->get();

    return response()->json([
        'success'      => true,
        'data'         => $serie,
        'indexaciones' => $indexaciones,
    ]);
}







    // Método para traer siempre todos los campos_extra de la tabla indexaciones
public function obtenerCamposExtraSerie(Request $request)
{
    $idSerie = $request->input('idSerie');

    if (!$idSerie) {
        return response()->json(['campos_extra' => []]);
    }

    $serie = \App\Models\Serie::find($idSerie);

    if (!$serie) {
        return response()->json(['campos_extra' => []]);
    }

    // Casteo a array
    $params = $serie->parametros_indexados;

    if (is_string($params)) {
        $params = json_decode($params, true);
    }

    if (!is_array($params)) {
        $params = [];
    }

    // Formato esperado {titulo: "..."}
    $resultado = array_map(fn($t) => ['titulo' => $t], $params);

    return response()->json(['campos_extra' => $resultado]);
}







public function archivos(Request $request)
{
    $request->validate([
        'id_proyecto' => 'required|integer',
        'archivos' => 'required|array',
        'archivos.*' => 'file|mimes:pdf,zip',
    ]);

    $proyectoId = $request->id_proyecto;
    $carpeta = storage_path("app/public/{$proyectoId}");

    if (!file_exists($carpeta)) mkdir($carpeta, 0755, true);

    $archivosProcesados = [];

    foreach ($request->file('archivos') as $archivo) {
        $extension = $archivo->getClientOriginalExtension();

        if ($extension === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($archivo->getRealPath()) === true) {
                $zip->extractTo($carpeta);
                $zip->close();

                $pdfsExtraidos = glob($carpeta . DIRECTORY_SEPARATOR . '*.pdf');
                foreach ($pdfsExtraidos as $pdfPath) {
                    $this->procesarPDF($pdfPath, $proyectoId, $archivosProcesados);
                }
            }
        } else {
            $rutaArchivo = $carpeta . DIRECTORY_SEPARATOR . $archivo->getClientOriginalName();
            $archivo->move($carpeta, $archivo->getClientOriginalName());
            $this->procesarPDF($rutaArchivo, $proyectoId, $archivosProcesados);
        }
    }

    return response()->json([
        'message' => 'Archivos procesados correctamente',
        'archivos' => $archivosProcesados,
    ]);
}

private function procesarPDF($archivo, $proyectoId, &$archivosProcesados)
{
    $pdf = new Fpdi();

    try {
        $pageCount = $pdf->setSourceFile($archivo);
    } catch (\Exception $e) {
        \Log::error("Error procesando PDF: {$archivo} - " . $e->getMessage());
        $pageCount = 1;
    }

    if ($pageCount > 1) {
        // ✅ Solo avisamos que tiene varias páginas y dejamos para separar
        $archivosProcesados[] = [
            'nombre' => basename($archivo),
            'paginas' => $pageCount,
            'mensaje' => "Este PDF tiene {$pageCount} páginas. Puedes separarlo antes de guardarlo."
        ];
        return; // No lo registramos aún
    }

    // 🔹 PDF de 1 página, registramos directamente
    $this->registrarPDF($archivo, $proyectoId, $archivosProcesados);
}

private function registrarPDF($archivo, $proyectoId, &$archivosProcesados)
{
    $nombreArchivo = basename($archivo);
    $rutaCarpeta = storage_path("app/public/documentos/seccion/{$proyectoId}");
    if (!file_exists($rutaCarpeta)) mkdir($rutaCarpeta, 0755, true);

    $nuevaRuta = $rutaCarpeta . DIRECTORY_SEPARATOR . $nombreArchivo;
    rename($archivo, $nuevaRuta);

    $rutaRelativa = "storage/documentos/seccion/{$proyectoId}/{$nombreArchivo}";

    $idIndexacion = DB::table('indexaciones')->insertGetId([
        'id_proyecto' => $proyectoId,
        'archivo_url' => $rutaRelativa,
        'estado'      => 1,
        'creado_en'   => now(),
        'updated_at'  => now(),
    ]);

    $archivosProcesados[] = [
        'id_indexacion' => $idIndexacion,
        'nombre'        => $nombreArchivo,
        'ruta_relativa' => $rutaRelativa,
        'url'           => asset($rutaRelativa)
    ];
}



public function separar(Request $request)
{
    $request->validate([
        'nombreArchivo' => 'required|string',
        'id_proyecto' => 'required|integer'
    ]);

    $nombreArchivo = $request->nombreArchivo;
    $idProyecto = $request->id_proyecto;

    $rutaArchivo = storage_path("app/public/documentos/{$idProyecto}/{$nombreArchivo}");

    if (!file_exists($rutaArchivo)) {
        return response()->json(['error' => 'Archivo no encontrado'], 404);
    }

    $pdfsSeparados = [];

    try {
        $pdf = new \setasign\Fpdi\Fpdi();
        $pageCount = $pdf->setSourceFile($rutaArchivo);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $newPdf = new \setasign\Fpdi\Fpdi();
            $newPdf->AddPage();
            $tplIdx = $newPdf->importPage($pageNo);
            $newPdf->useTemplate($tplIdx);

            $tmpName = 'pagina_' . $pageNo . '_' . $nombreArchivo;

            $tmpPdfPath = storage_path("app/public/tmp_uploads/{$tmpName}");
            $newPdf->Output($tmpPdfPath, 'F');

            $fileContent = file_get_contents($tmpPdfPath);
            $pdfsSeparados[] = [
                'nombre' => $tmpName,
                'file' => base64_encode($fileContent)
            ];

            unlink($tmpPdfPath);
        }

        return response()->json($pdfsSeparados);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}










public function archivosSerie(Request $request)
{
    $request->validate([
        'archivos' => 'required|array',
        'archivos.*' => 'file|mimes:pdf,zip',
        'id_serie' => 'required|integer', // 👈 Recibimos idSerie
    ]);

    $idSerie = $request->input('id_serie'); // ✅ Obtenemos idSerie

    // Carpeta genérica temporal para subir archivos
    $carpeta = storage_path("app/public/archivos"); 
    if (!file_exists($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    $archivosProcesados = [];

    foreach ($request->file('archivos') as $archivo) {
        $extension = $archivo->getClientOriginalExtension();

        if ($extension === 'zip') {
            $zip = new \ZipArchive;
            if ($zip->open($archivo->getRealPath()) === true) {
                $zip->extractTo($carpeta);
                $zip->close();

                $pdfsExtraidos = glob($carpeta . DIRECTORY_SEPARATOR . '*.pdf');
                foreach ($pdfsExtraidos as $pdfPath) {
                    $this->procesarPDFSerie($pdfPath, $idSerie, $archivosProcesados);
                }
            }
        } else {
            $rutaArchivo = $carpeta . DIRECTORY_SEPARATOR . $archivo->getClientOriginalName();
            $archivo->move($carpeta, $archivo->getClientOriginalName());
            $this->procesarPDFSerie($rutaArchivo, $idSerie, $archivosProcesados);
        }
    }

    return response()->json([
        'message' => 'Archivos procesados correctamente',
        'archivos' => $archivosProcesados,
    ]);
}


private function procesarPDFSerie($archivo, $idSerie, &$archivosProcesados)
{
    $pdf = new Fpdi();

    try {
        $pageCount = $pdf->setSourceFile($archivo);
    } catch (\Exception $e) {
        \Log::error("Error procesando PDF: {$archivo} - " . $e->getMessage());
        $pageCount = 1;
    }

    if ($pageCount > 1) {
        $archivosProcesados[] = [
            'nombre' => basename($archivo),
            'paginas' => $pageCount,
            'mensaje' => "Este PDF tiene {$pageCount} páginas. Puedes separarlo antes de guardarlo."
        ];
        return; 
    }

    // PDF de 1 página, registramos directamente
    $this->registrarPDFSerie($archivo, $idSerie, $archivosProcesados);
}


private function registrarPDFSerie($archivo, $idSerie, &$archivosProcesados)
{
    $nombreArchivo = basename($archivo);
    $rutaCarpeta = storage_path("app/public/documentos/serie/{$idSerie}");
    if (!file_exists($rutaCarpeta)) mkdir($rutaCarpeta, 0755, true);

    $nuevaRuta = $rutaCarpeta . DIRECTORY_SEPARATOR . $nombreArchivo;
    rename($archivo, $nuevaRuta);

    $rutaRelativa = "storage/documentos/serie/{$idSerie}/{$nombreArchivo}";

    $idIndexacion = DB::table('indexaciones')->insertGetId([
        'id_serie'    => $idSerie,       // ✅ Guardamos el idSerie
        'archivo_url' => $rutaRelativa,
        'estado'      => 2,              // ✅ Estado 2 por defecto
        'creado_en'   => now(),
        'updated_at'  => now(),
    ]);

    $archivosProcesados[] = [
        'id_indexacion' => $idIndexacion,
        'nombre'        => $nombreArchivo,
        'ruta_relativa' => $rutaRelativa,
        'url'           => asset($rutaRelativa)
    ];
}


/*

public function desunirPdf(Request $request)
{
    $request->validate([
        'archivo_pdf' => 'required|file|mimes:pdf'
    ]);

    $apiKey = env('OPENAI_API_KEY');

    $pdfFile = $request->file('archivo_pdf');

    $tempFolder = storage_path('app/temp');
    if (!file_exists($tempFolder)) {
        mkdir($tempFolder, 0777, true);
    }

    $filename = uniqid() . '.pdf';
    $fullPath = $tempFolder . DIRECTORY_SEPARATOR . $filename;
    $pdfFile->move($tempFolder, $filename);

    $pdf = new Fpdi();
    $pageCount = $pdf->setSourceFile($fullPath);

    $pagesPath = $tempFolder . DIRECTORY_SEPARATOR . 'pages_' . uniqid();
    if (!file_exists($pagesPath)) {
        mkdir($pagesPath, 0777, true);
    }

    $zip = new ZipArchive;
    $zipName = 'paginas_separadas_' . uniqid() . '.zip';
    $zipPath = $tempFolder . DIRECTORY_SEPARATOR . $zipName;
    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        return response()->json(['error' => 'No se pudo crear el ZIP'], 500);
    }

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $pdfNew = new Fpdi();
        $pdfNew->setSourceFile($fullPath);
        $pdfNew->AddPage();
        $tplId = $pdfNew->importPage($pageNo);
        $pdfNew->useTemplate($tplId);

        $pagePdfName = "pagina_{$pageNo}.pdf";
        $pagePdfPath = $pagesPath . DIRECTORY_SEPARATOR . $pagePdfName;
        $pdfNew->Output('F', $pagePdfPath);

        // Convertir PDF página a imagen PNG para OCR y mejorar imagen
        $imagePath = $pagesPath . DIRECTORY_SEPARATOR . "pagina_{$pageNo}.png";

        $imagick = new \Imagick();
        $imagick->setResolution(300, 300);
        $imagick->readImage($pagePdfPath);

        // Mejorar la imagen para OCR
        $imagick->setImageColorspace(\Imagick::COLORSPACE_GRAY);
        $imagick->contrastImage(true);
        $imagick->sharpenImage(1, 0.5);
        // Opcional: despeckle para limpiar ruido
        // $imagick->despeckleImage();

        $imagick->setImageFormat('png');
        $imagick->writeImage($imagePath);
        $imagick->clear();
        $imagick->destroy();

        // Leer la imagen mejorada y convertir a base64 para OpenAI
        $imgData = base64_encode(file_get_contents($imagePath));

        // Preparar payload para OpenAI (usando chat completions con imagen)
        $payload = [
            "model" => "gpt-4o",
            "messages" => [
                [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "text",
                            "text" => "Extrae TODO el texto visible de esta imagen. No expliques nada, solo devuelve el texto tal como aparece."
                        ],
                        [
                            "type" => "image_url",
                            "image_url" => [
                                "url" => "data:image/png;base64,{$imgData}"
                            ]
                        ]
                    ]
                ]
            ],
            "max_tokens" => 3000,
        ];

        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        $responseBody = json_decode($response->getBody(), true);
        $extractedText = '';
        if (isset($responseBody['choices'][0]['message']['content'])) {
            $extractedText = $responseBody['choices'][0]['message']['content'];
        }

        // Guardar texto en archivo .txt con el mismo nombre base que el PDF de la página
        $txtPath = $pagesPath . DIRECTORY_SEPARATOR . "pagina_{$pageNo}.txt";
        file_put_contents($txtPath, $extractedText);

        // Añadir PDF y TXT al ZIP
        $zip->addFile($pagePdfPath, $pagePdfName);
        $zip->addFile($txtPath, "pagina_{$pageNo}.txt");

        // Opcional: borrar la imagen PNG temporal si quieres
        unlink($imagePath);
    }

    $zip->close();

    return response()->download($zipPath)->deleteFileAfterSend(true);
}
*/
/*

public function desunirPdf(Request $request)
{
    $request->validate([
        'archivo_pdf' => 'required|file|mimes:pdf'
    ]);

    $pdfFile = $request->file('archivo_pdf');

    $tempFolder = storage_path('app/temp');
    if (!file_exists($tempFolder)) {
        mkdir($tempFolder, 0777, true);
    }

    $idProceso = uniqid();
    $filename = $idProceso . '.pdf';
    $fullPath = $tempFolder . DIRECTORY_SEPARATOR . $filename;
    $pdfFile->move($tempFolder, $filename);

    // Guardamos ruta en cache para usar en stream y descarga
    cache()->put('proceso_' . $idProceso . '_file', $fullPath, 3600);

    // Retornamos solo el idProceso (no hacemos proceso pesado acá)
    return response()->json(['idProceso' => $idProceso]);
}


public function streamProgresoPdf($idProceso)
{
    ignore_user_abort(true);
    set_time_limit(0);

    // Limpia buffers PHP y desactiva buffering
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('Access-Control-Allow-Origin: *');

    // Enviar un ping para desbloquear buffers del navegador
    echo ": ping\n\n";
    flush();

    $fullPath = cache()->get('proceso_' . $idProceso . '_file');
    if (!$fullPath) {
        echo "data: Error: proceso no encontrado\n\n";
        flush();
        return;
    }

    $pdf = new \setasign\Fpdi\Fpdi();
    $pageCount = $pdf->setSourceFile($fullPath);

    $pagesPath = storage_path('app/temp/pages_' . $idProceso);
    if (!file_exists($pagesPath)) mkdir($pagesPath, 0777, true);

    $zip = new \ZipArchive();
    $zipName = 'paginas_separadas_' . $idProceso . '.zip';
    $zipPath = storage_path('app/temp/' . $zipName);
    if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
        echo "data: Error al crear ZIP\n\n";
        flush();
        return;
    }

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $pdfNew = new \setasign\Fpdi\Fpdi();
        $pdfNew->setSourceFile($fullPath);
        $pdfNew->AddPage();
        $tplId = $pdfNew->importPage($pageNo);
        $pdfNew->useTemplate($tplId);

        $pagePdfName = "pagina_{$pageNo}.pdf";
        $pagePdfPath = $pagesPath . DIRECTORY_SEPARATOR . $pagePdfName;
        $pdfNew->Output('F', $pagePdfPath);

        $zip->addFile($pagePdfPath, $pagePdfName);

        echo "data: Imagen desunida {$pageNo}\n\n";
        flush();

        // Opcional: para probar el envío espaciado en el tiempo
        usleep(500000); // 0.5 segundos (500,000 microsegundos)
    }

    $zip->close();

    echo "data: Proceso finalizado\n\n";
    flush();
}






public function descargarZip($idProceso)
{
    $zipName = 'paginas_separadas_' . $idProceso . '.zip';
    $zipPath = storage_path('app/temp/' . $zipName);

    if (!file_exists($zipPath)) {
        return response()->json(['error' => 'Archivo ZIP no encontrado'], 404);
    }

    return response()->download($zipPath)->deleteFileAfterSend(true);
}

*/



public function TraeIndexacion(Request $request)
{
    try {

        // ================== ESCENARIO 1 ==================
        // SI YA VIENE idIndexacion → BUSCAR DIRECTO
        if (!empty($request->idIndexacion)) {

            $indexacion = Indexacion::where('id_indexacion', $request->idIndexacion)->first();

            return response()->json([
                'success' => true,
                'escenario' => 'por_idIndexacion',
                'data' => $indexacion
            ]);
        }

        // ================== ESCENARIO 2 ==================
        // SI NO VIENE idIndexacion → BUSCAR POR SERIE + SUBSERIE + EMPRESA

        $query = Indexacion::where('id_serie', $request->idSerie)
            ->where('id_empresa', $request->id_empresa);

        if (!empty($request->idSubSerie)) {
            $query->where('id_subserie', $request->idSubSerie);
        } else {
            $query->whereNull('id_subserie');
        }

        $indexacion = $query->first();

        return response()->json([
            'success' => true,
            'escenario' => 'por_serie_subserie',
            'data' => $indexacion
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'error' => 'Error al obtener la indexación',
            'detalle' => $e->getMessage()
        ], 500);
    }
}


}
