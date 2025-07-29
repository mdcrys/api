<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Documento;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Tcpdf\Fpdi;

class DocumentoController extends Controller
{
   // 📥 Guardar documentos con OCR (sin insertar texto visible)
   public function store(Request $request)
{
    $documentos = [];
    $erroresOCR = [];

    if ($request->hasFile('archivos')) {
        foreach ($request->file('archivos') as $archivo) {
            // Obtener nombre original del archivo (ej: contrato123.pdf)
            $nombreOriginal = $archivo->getClientOriginalName();
            $pathOriginal = $archivo->storeAs('documentos/originales', $nombreOriginal, 'public');
            $rutaOriginal = storage_path('app/public/' . $pathOriginal);

            // Definir ruta OCR con el mismo nombre original
            $pathOCR = 'documentos/ocr/' . $nombreOriginal;
            $rutaOCR = storage_path('app/public/' . $pathOCR);

            // Crear carpeta si no existe
            $dirOCR = dirname($rutaOCR);
            if (!file_exists($dirOCR)) mkdir($dirOCR, 0775, true);

            // Eliminar si ya existe
            if (file_exists($rutaOCR)) unlink($rutaOCR);

            // Ejecutar OCR con ocrmypdf
            $output = [];
            $returnVar = 0;
            $command = "ocrmypdf --force-ocr " . escapeshellarg($rutaOriginal) . " " . escapeshellarg($rutaOCR) . " 2>&1";
            exec($command, $output, $returnVar);
            $joinedOutput = implode("\n", $output);

            if ($returnVar !== 0) {
                if (strpos($joinedOutput, 'DigitalSignatureError') !== false) {
                    $erroresOCR[] = $nombreOriginal . ' tiene firma digital. No se pudo procesar OCR.';
                    Log::warning("PDF con firma digital omitido: {$rutaOriginal}");
                } else {
                    Log::error("Error ejecutando OCRmyPDF para archivo: {$rutaOriginal}");
                    Log::error("Salida OCRmyPDF:", $output);
                    $erroresOCR[] = 'Error al procesar ' . $nombreOriginal;
                }
                continue;
            }

            // Registrar en base de datos
            $documentos[] = Documento::create([
                'tipologia' => $request->tipologia,
                'tema' => $request->tema,
                'id_estanteria' => $request->id_estanteria,
                'id_caja' => $request->id_caja,
                'fecha' => $request->fecha,
                'archivo_url' => $pathOCR,
                'estado' => 1,
                'id_seccion' => $request->id_seccion,
                'id_modulo' => $request->id_modulo,
            ]);
        }
    }

    return response()->json([
        'success' => true,
        'mensaje' => 'Proceso completado con OCR (sin texto visible)',
        'documentos' => $documentos,
        'errores' => $erroresOCR,
    ]);
}



public function index(Request $request)
{
    $query = Documento::query();

    if ($request->filled('tipologia')) {
        $query->where('tipologia', 'like', '%' . $request->tipologia . '%');
    }

    if ($request->filled('tema')) {
        $query->where('tema', 'like', '%' . $request->tema . '%');
    }

    if ($request->filled('estanteria')) {
        $query->where('id_estanteria', $request->estanteria);
    }

    if ($request->filled('caja')) {
        $query->where('id_caja', $request->caja);
    }

    if ($request->filled('id_seccion')) {
        $query->where('id_seccion', $request->id_seccion);
    }

    $documentos = $query->orderBy('id_documento', 'desc')->paginate(10);

    return response()->json([
        'success' => true,
        'documentos' => $documentos
    ]);
}











    // Mostrar uno
    public function show($id)
    {
        $doc = Documento::find($id);
        return response()->json($doc);
    }

    // Eliminar
    public function destroy($id)
    {
        Documento::destroy($id);
        return response()->json(['success' => true]);
    }
}
