<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Indexacion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


use ZipArchive;
use Smalot\PdfParser\Parser; // O la librería que uses para PDF
use setasign\Fpdi\Fpdi; // Para manipular PDFs página a página


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
   public function obtenerCamposExtra(Request $request)
    {
        $idModulo = $request->input('idModulo');

        if (!$idModulo) {
            return response()->json(['error' => 'Falta idModulo'], 400);
        }

        // Obtener todos los campos_extra de ese módulo
        $registros = Indexacion::where('id_modulo', $idModulo)
            ->pluck('campos_extra');

        $titulosUnicos = collect($registros)
            ->flatten(1)
            ->unique('titulo')
            ->values();

        return response()->json([
            'campos_extra' => $titulosUnicos,
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












public function desunirPdf(Request $request)
{
    $request->validate([
        'archivo_pdf' => 'required|file|mimes:pdf'
    ]);

    $pdfFile = $request->file('archivo_pdf');

    // Crear carpeta temporal si no existe
    $tempFolder = storage_path('app/temp');
    if (!file_exists($tempFolder)) {
        mkdir($tempFolder, 0777, true);
    }

    // Guardar archivo manualmente con nombre único
    $filename = uniqid() . '.pdf';
    $fullPath = $tempFolder . DIRECTORY_SEPARATOR . $filename;
    $pdfFile->move($tempFolder, $filename);

    // Abrir PDF con FPDI para contar páginas
    $pdf = new Fpdi();
    $pageCount = $pdf->setSourceFile($fullPath);

    // Carpeta temporal para páginas separadas
    $pagesPath = $tempFolder . DIRECTORY_SEPARATOR . 'pages_' . uniqid();
    if (!file_exists($pagesPath)) {
        mkdir($pagesPath, 0777, true);
    }

    // Extraer cada página a un PDF separado
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        $pdfNew = new Fpdi();
        $pdfNew->setSourceFile($fullPath); // <<== Aquí es clave
        $pdfNew->AddPage();
        $tplId = $pdfNew->importPage($pageNo);
        $pdfNew->useTemplate($tplId);

        $pageFile = $pagesPath . DIRECTORY_SEPARATOR . "pagina_{$pageNo}.pdf";
        $pdfNew->Output('F', $pageFile);
    }

    // Crear ZIP con todas las páginas
    $zipName = 'paginas_separadas_' . uniqid() . '.zip';
    $zipPath = $tempFolder . DIRECTORY_SEPARATOR . $zipName;

    $zip = new ZipArchive;
    if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        $files = scandir($pagesPath);
        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) continue;
            $zip->addFile($pagesPath . DIRECTORY_SEPARATOR . $file, $file);
        }
        $zip->close();
    } else {
        return response()->json(['error' => 'No se pudo crear el ZIP'], 500);
    }

    // Opcional: borrar archivos temporales si quieres
    // Storage::deleteDirectory('temp');

    // Retornar ZIP para descarga y borrar después
    return response()->download($zipPath)->deleteFileAfterSend(true);
}










}
