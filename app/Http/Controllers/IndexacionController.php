<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Indexacion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class IndexacionController extends Controller
{
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

    // Calcular el siguiente contador global
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
            continue;
        }

        $rutaOCR = $carpeta . DIRECTORY_SEPARATOR . "{$nombreBase}_MODULO{$moduloId}_{$contadorGlobal}_OCR.pdf";

        $output = [];
        $returnVar = 0;
        $command = "ocrmypdf --force-ocr " . escapeshellarg($rutaArchivo) . " " . escapeshellarg($rutaOCR) . " 2>&1";
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
            continue;
        }

        // Reemplazar el original por el que tiene OCR
        if (!rename($rutaOCR, $rutaArchivo)) {
            Log::error("No se pudo reemplazar el archivo original con OCR: {$rutaArchivo}");
            $erroresOCR[] = "No se pudo reemplazar el archivo original con OCR para {$nuevoNombre}";
            continue;
        }

        $rutaRelativa = "storage/public/{$moduloId}/{$nuevoNombre}";
        $url = url($rutaRelativa);

        $archivosProcesados[] = [
            'nombre' => $nuevoNombre,
            'ruta_relativa' => $rutaRelativa,
            'url' => $url
        ];
    }

    return response()->json([
        'message' => 'Archivos guardados y procesados con OCR',
        'archivos' => $archivosProcesados,
        'errores_ocr' => $erroresOCR,
    ]);
}



public function store(Request $request)
{
    $request->validate([
        'idModulo' => 'required|integer',
        'campos' => 'required|string',
        'archivos.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
    ]);

    $idModulo = $request->input('idModulo');
    $campos = json_decode($request->input('campos'), true);

    if ($campos === null) {
        return response()->json(['error' => 'Campos JSON inválido'], 400);
    }

    $archivosGuardados = [];
    if ($request->hasFile('archivos')) {
        foreach ($request->file('archivos') as $archivo) {
            if ($archivo->isValid()) {
                $carpetaDestino = 'public/documentos';

                // Obtener nombre base y extensión
                $nombreBase = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $archivo->getClientOriginalExtension();

                // Crear nombre único para evitar sobrescritura
                $nombreArchivo = $nombreBase . '_' . time() . '.' . $extension;

                // Guardar archivo con el nombre único
                $ruta = $archivo->storeAs($carpetaDestino, $nombreArchivo);

                $archivosGuardados[] = $ruta;
            }
        }
    }

    $indexacion = Indexacion::create([
        'id_modulo' => $idModulo,
        'campos_extra' => $campos,
        'archivo_url' => json_encode($archivosGuardados), // Guardar rutas como JSON
        'estado' => 1,
        'creado_en' => now(),
        'updated_at' => now(),
    ]);

    return response()->json([
        'mensaje' => 'Datos guardados correctamente',
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
    public function obtenerCamposExtra()
    {
        // Traemos solo la columna campos_extra de todos los registros
        $camposExtras = Indexacion::select('campos_extra')->get();

        return response()->json([
            'campos_extra' => $camposExtras,
        ]);
    }



    public function buscarDocumento(Request $request)
{
    $idModulo = $request->input('id_modulo');
    $campoValor = $request->input('campo_valor');
    $page = $request->input('page', 1);
    $perPage = 10; // Por ejemplo

    $query = Indexacion::query();

    // Filtrar por id_modulo
    if ($idModulo) {
        $query->where('id_modulo', $idModulo);
    }

    // Si existe texto para buscar en campos_extra
    if ($campoValor) {
        // Dividir la cadena de búsqueda en palabras para hacer búsqueda parcial
        $palabras = explode(' ', $campoValor);

        // Buscar en JSON: MySQL 5.7+ permite usar JSON_CONTAINS, pero aquí buscamos en campo 'valor'
        $query->where(function($q) use ($palabras) {
            foreach ($palabras as $palabra) {
                $palabra = trim($palabra);
                if ($palabra !== '') {
                    // Hacemos búsqueda LIKE en el JSON codificado en campos_extra buscando la palabra dentro de "valor"
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












}
