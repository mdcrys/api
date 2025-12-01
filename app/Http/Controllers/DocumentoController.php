<?php




namespace App\Http\Controllers;






use phpseclib3\File\PKCS12;
use phpseclib3\File\X509;
use phpseclib3\Crypt\PrivateKey;

use Illuminate\Http\Request;
use App\Models\Documento;
use App\Models\User;
use App\Models\Proyecto; // 🔗 Asegúrate de importar el modelo
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Facades\DB;
use App\Models\Empresa;
use phpseclib3\Crypt\RSA;


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




public function getPermisosCarpeta(Request $request)
    {
        $data = $request->all();

        // Validar que vengan los 3 campos
        if (!isset($data['id_carpeta'], $data['id_empresa'], $data['id_usuario'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Faltan datos: id_carpeta, id_empresa o id_usuario'
            ], 400);
        }

        $id_carpeta = $data['id_carpeta'];
        $id_empresa = $data['id_empresa'];
        $id_usuario = $data['id_usuario'];

        // Buscar los permisos del usuario sobre la carpeta
        $permisos = DB::table('permisos_carpetas')
            ->where('id_carpeta', $id_carpeta)
            ->where('id_usuario', $id_usuario)
            ->first();

        if (!$permisos) {
            // Si no existen permisos aún, devolver false en todos
            return response()->json([
                'puede_ver' => 0,
                'puede_subir' => 0,
                'puede_editar' => 0,
                'puede_eliminar' => 0
            ]);
        }

        return response()->json([
            'puede_ver' => $permisos->puede_ver,
            'puede_subir' => $permisos->puede_subir,
            'puede_editar' => $permisos->puede_editar,
            'puede_eliminar' => $permisos->puede_eliminar,
        ]);
    }




    public function GuardarPermisosCarpetas(Request $request)
    {
        $data = $request->all();

        if (!isset($data['id_carpeta']) || !isset($data['usuarios']) || !isset($data['id_usuario_logeado'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Falta id_carpeta, usuarios o id_usuario_logeado'
            ], 400);
        }

        $id_carpeta = $data['id_carpeta'];
        $usuarios = $data['usuarios'];
        $id_usuario_logeado = $data['id_usuario_logeado'];

        foreach ($usuarios as $u) {
            // Usar updateOrInsert para no duplicar registros
            DB::table('permisos_carpetas')->updateOrInsert(
                [
                    'id_usuario' => $u['id_usuario'],
                    'id_carpeta' => $id_carpeta
                ],
                [
                    'puede_ver' => $u['puede_ver'] ? 1 : 0,
                    'puede_subir' => $u['puede_subir'] ? 1 : 0,
                    'puede_editar' => $u['puede_editar'] ? 1 : 0,
                    'puede_eliminar' => $u['puede_eliminar'] ? 1 : 0,
                    'modificado_por' => $id_usuario_logeado,  // <-- guardamos el usuario logeado
                    'updated_at' => now(),                    // <-- opcional, si tienes timestamps
                ]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Permisos guardados correctamente'
        ]);
    }


    
 public function getPermisosPorUsuario(Request $request)
    {
        $request->validate([
            'id_usuario' => 'required|integer',
        ]);

        $idUsuario = $request->id_usuario;

        // Traer solo los permisos de las carpetas que puede ver
        $permisos = DB::table('permisos_carpetas')
            ->where('id_usuario', $idUsuario)
            ->select(
                'id_carpeta',
                'puede_ver',
                'puede_subir',
                'puede_editar',
                'puede_eliminar'
            )
            ->get();

        return response()->json($permisos);
    }




public function Firmar(Request $request)
{
    try {
        // 1️⃣ Recibir datos enviados desde Angular
        $pdfOriginal = $request->input('pdfOriginal');
        $empresaId = $request->input('empresaId');

        if (!$pdfOriginal || !$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan datos: PDF o empresaId.'
            ], 400);
        }

        // 2️⃣ Validar existencia del PDF
        $pdfPath = public_path($pdfOriginal);
        if (!file_exists($pdfPath)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo PDF no existe en el servidor.'
            ], 404);
        }

        // 3️⃣ Traer datos de la empresa
        $empresa = Empresa::find($empresaId);
        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la empresa.'
            ], 404);
        }

        // 4️⃣ Validar existencia del archivo .p12
        $p12Path = public_path('storage/' . $empresa->ruta_firma); // Ajuste importante
        if (!file_exists($p12Path)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el archivo de firma (.p12).',
                'rutaIntentada' => $p12Path
            ], 404);
        }

        // 🔹 5️⃣ Leer el .p12 usando phpseclib en lugar de OpenSSL
        $p12 = new PKCS12(file_get_contents($p12Path), $empresa->contrasena_firma);
        $cert = $p12->getCertificate();
        $pkey = $p12->getPrivateKey();

        if (!$cert || !$pkey) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer la firma digital con phpseclib.'
            ], 400);
        }

        // 6️⃣ Retornar información para depuración y confirmación
        return response()->json([
            'success' => true,
            'pdfPath' => $pdfPath,
            'empresa' => [
                'id_empresa' => $empresa->id_empresa,
                'nombre_empresa' => $empresa->nombre_empresa,
                'ruc_empresa' => $empresa->ruc_empresa,
                'ruta_firma' => $empresa->ruta_firma,
                'contrasena_firma' => $empresa->contrasena_firma
            ],
            'firmaValida' => true,
            'certs' => [
                'cert' => $cert->saveX509(), // para depuración
                'pkey' => $pkey->toString('PKCS8') // para depuración
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener datos del PDF o empresa: ' . $e->getMessage()
        ], 500);
    }
}







public function listarDocumentosPorSerie(Request $request)
{
    $idSerie = $request->input('idSerie');
    $idSubserie = $request->input('idSubserie');

    if (!$idSerie && !$idSubserie) {
        return response()->json([
            'error' => 'Debe enviar idSerie o idSubserie'
        ], 400);
    }

    $consulta = DB::table('documentos');

    // ✅ Usar solo id_serie_subserie
    $idSerieSubserie = $idSubserie ?? $idSerie;
    $consulta->where('id_serie_subserie', $idSerieSubserie);

    $documentos = $consulta
        ->orderBy('created_at', 'desc')
        ->get([
            'id_documento as id',
            'nombre_archivo as nombre',
            'created_at as fecha'
        ]);

    return response()->json([
        'documentos' => $documentos
    ]);
}










public function subirDocumentosSerie(Request $request)
{
    $request->validate([
        'documentos.*' => 'required|file|mimes:pdf',
        'id_serie'     => 'nullable|integer',
        'id_subserie'  => 'nullable|integer',
        'id_empresa'   => 'required|integer',
        'usuario_id'   => 'required|integer', // ✅ validar usuario_id
    ]);

    $archivos        = $request->file('documentos');
    $idEmpresa       = $request->id_empresa;
    $idSerieSubserie = $request->id_subserie ?? $request->id_serie;
    $usuarioId       = $request->usuario_id; // ✅ obtener usuario_id

    // 👉 Ruta final: storage/app/public/documentos/{empresa}/{serie/subserie}
    $rutaFinal = storage_path("app/public/documentos/{$idEmpresa}/{$idSerieSubserie}");

    // Crear carpetas si no existen
    if (!file_exists($rutaFinal)) {
        mkdir($rutaFinal, 0777, true);
    }

    foreach ($archivos as $archivo) {

        $nombre    = $archivo->getClientOriginalName();
        $extension = $archivo->getClientOriginalExtension();
        $tamano    = $archivo->getSize();

        // ✅ Mover el archivo
        $archivo->move($rutaFinal, $nombre);

        // ✅ Guardar en la BD
        Documento::create([
            'id_empresa'        => $idEmpresa,
            'id_serie_subserie' => $idSerieSubserie,
            'usuario_registro'  => $usuarioId,  // ✅ Guardar usuario
            'nombre_archivo'    => $nombre,
            'ruta_archivo'      => $nombre,
            'tipo_archivo'      => $extension,
            'tamano_archivo'    => $tamano,
            'estado'            => 1,
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Documentos subidos correctamente'
    ]);
}











public function obtenerDocumento(Request $request)
{
    $idDocumento = $request->input('idDocumento');
    $idEmpresa = $request->input('idEmpresa');
    $idSerieSubserie = $request->input('idSerieSubserie');

    if (!$idDocumento || !$idEmpresa || !$idSerieSubserie) {
        return response()->json(['success' => false, 'message' => 'Faltan parámetros'], 400);
    }

    // Buscar solo por ID del documento
    $documento = DB::table('documentos')->where('id_documento', $idDocumento)->first();

    if (!$documento) {
        return response()->json(['success' => false, 'message' => 'Documento no encontrado'], 404);
    }

    // Construir ruta usando idEmpresa y idSerieSubserie para la carpeta
    $rutaPublica = asset("storage/documentos/{$idEmpresa}/{$idSerieSubserie}/{$documento->nombre_archivo}");

    return response()->json([
        'success' => true,
        'data' => ['ruta' => $rutaPublica]
    ]);
}




public function getCamposByDocumento($id)
{
    if (!$id) {
        return response()->json([
            'success' => false,
            'message' => 'ID de documento no enviado'
        ], 400);
    }

    // 1. Buscar el documento
    $documento = DB::table('documentos')
                    ->where('id_documento', $id)
                    ->first();

    if (!$documento) {
        return response()->json([
            'success' => false,
            'message' => 'Documento no encontrado'
        ], 404);
    }

    // 2. Obtener la serie/subserie del documento
    $idSerieSubserie = $documento->id_serie_subserie;

    if (!$idSerieSubserie) {
        return response()->json([
            'success' => false,
            'message' => 'El documento no tiene una serie/subserie asignada'
        ], 404);
    }

    // 3. Buscar la serie
    $serie = DB::table('serie')
                ->where('id_serie', $idSerieSubserie)
                ->first();

    if (!$serie) {
        return response()->json([
            'success' => false,
            'message' => 'Serie no encontrada'
        ], 404);
    }

    // 4. Obtener parámetros indexados (JSON)
    if (!$serie->parametros_indexados) {
        return response()->json([
            'success' => false,
            'message' => 'Esta serie no tiene parámetros de indexación'
        ], 404);
    }

    // 5. Convertir JSON a array
    $parametros = json_decode($serie->parametros_indexados, true);

    return response()->json([
        'success' => true,
        'id_documento' => $id,
        'id_serie' => $idSerieSubserie,
        'data' => $parametros
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
