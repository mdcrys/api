<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Documento;
use App\Models\User;
use App\Models\Proyecto; // 🔗 Asegúrate de importar el modelo
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



public function getProyectosEscalera()
{
    $proyectos = Proyecto::with([
        // 🔹 Subsecciones (nivel 1)
        'subsecciones' => function($query) {
            $query->with([
                // Indexaciones propias de la subsección
                'indexaciones',
                // Subsecciones hijas (nivel 2)
                'subsecciones' => function($subquery) {
                    $subquery->with([
                        // Indexaciones del segundo nivel
                        'indexaciones',
                        // Series dentro del segundo nivel
                        'series' => function($seriesQuery) {
                            $seriesQuery->with([
                                'indexaciones',
                                // Hijos recursivos dentro de las series
                                'hijosRecursivos.indexaciones'
                            ]);
                        },
                        // 🔁 Subsecciones hijas de las subsecciones (nivel 3 y más)
                        'subsecciones.series.indexaciones',
                        'subsecciones.subsecciones.series.indexaciones',
                    ]);
                },
                // Series en la subsección de primer nivel
                'series' => function($seriesQuery) {
                    $seriesQuery->with([
                        'indexaciones',
                        'hijosRecursivos.indexaciones'
                    ]);
                },
            ]);
        },
        // 🔹 Relaciones directas del proyecto raíz
        'indexaciones',
        'series.indexaciones',
        'series.hijosRecursivos.indexaciones'
    ])
    ->whereNull('padre_id')
    ->get();

    return response()->json($proyectos);
}










public function getImagenesPDF(Request $request)
    {
         $archivo = $request->query('archivo'); // ej: storage/documentos/serie/4/002014000182618.pdf
    $fullPath = public_path($archivo);

    if (!file_exists($fullPath)) {
        return response()->json(['message' => 'Archivo no encontrado'], 404);
    }

    return response()->file($fullPath, [
        'Content-Type' => 'application/pdf'
    ]);
    }


    public function Pdf(Request $request)
{
    $request->validate([
        'archivo_url' => 'required|string'
    ]);

    $archivoUrl = $request->input('archivo_url');
    $path = public_path($archivoUrl);

    if (!file_exists($path)) {
        return response()->json([
            'success' => false,
            'message' => 'PDF no encontrado.',
            'archivo' => $archivoUrl
        ], 404);
    }

    try {
        $imagick = new \Imagick();
        $imagick->setResolution(150, 150);
        $imagick->readImage($path);

        $imagenes = [];

        foreach ($imagick as $pagina) {
            $pagina->setImageFormat('png');
            $imagenes[] = base64_encode($pagina->getImageBlob());
        }

        return response()->json([
            'success' => true,
            'message' => 'PDF convertido a imágenes correctamente.',
            'imagenes' => $imagenes
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al convertir PDF a imágenes: ' . $e->getMessage()
        ], 500);
    }
}




public function UsuariosEmpresa(Request $request)
{
    // Validar que venga id_empresa
    $request->validate([
        'id_empresa' => 'required|integer|exists:users,id_empresa'
    ]);

    $idEmpresa = $request->input('id_empresa');

    // Obtener usuarios de la empresa
    $usuarios = User::where('id_empresa', $idEmpresa)
                    ->whereNull('deleted_at') // opcional, si quieres excluir eliminados
                    ->get([
                        'id', 
                        'name', 
                        'surname', 
                        'email', 
                        'phone', 
                        'role_id', 
                        'sucursale_id',
                        'type_document',
                        'n_document',
                        'address',
                        'gender',
                        'avatar'
                    ]);

    return response()->json([
        'success' => true,
        'usuarios' => $usuarios
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
