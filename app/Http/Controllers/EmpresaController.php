<?php

namespace App\Http\Controllers;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use App\Models\Empresa;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    public function index(Request $request)
{
    $search = $request->get("search");

    $empresas = Empresa::where("nombre_empresa", "like", "%" . $search . "%")
        ->orderBy("id_empresa", "desc")
        ->paginate(25);

    return response()->json([
        "total" => $empresas->total(),
        "empresas" => $empresas->map(function ($empresa) {
            return [
                "id" => $empresa->id_empresa, // Cambiado aquí
                "nombre_empresa" => $empresa->nombre_empresa,
                "ruc_empresa" => $empresa->ruc_empresa,
                "telefono" => $empresa->telefono,
                "correo" => $empresa->correo,
                "imagen" => $empresa->imagen_empresa ? env("APP_URL") . "storage/" . $empresa->imagen_empresa : null,
                "estado" => $empresa->estado ?? 1,
                "created_at" => $empresa->created_at?->format("Y-m-d h:i A")
            ];
        }),
    ]);
}




public function store(Request $request)
{
    // Verifica si el RUC ya existe
    if ($request->has('ruc_empresa')) {
        $existe = Empresa::where("ruc_empresa", $request->ruc_empresa)->first();
        if ($existe) {
            return response()->json([
                "message" => 403,
                "message_text" => "El RUC ya existe"
            ]);
        }
    }

    // Procesar logo
    if ($request->hasFile("logo")) {
        $path = $request->file("logo")->store("empresas", "public");
        $request->merge(["imagen_empresa" => $path]);
    }

    // Crear la empresa
    $empresa = Empresa::create([
        'nombre_empresa' => $request->input('nombre_empresa'),
        'ruc_empresa' => $request->input('ruc_empresa', ''),
        'telefono' => $request->input('telefono', ''),
        'correo' => $request->input('correo', ''),
        'direccion' => $request->input('direccion', ''),
        'imagen_empresa' => $request->input('imagen_empresa', null),
        'estado' => $request->input('estado', 1),
    ]);

    // Crear el usuario administrador vinculado a esta empresa
    $usuario = new \App\Models\User();
    $usuario->name = $request->input('admin_nombre');
    $usuario->surname = $request->input('admin_apellido');
    $usuario->email = $request->input('admin_correo');
    $usuario->password = bcrypt($request->input('admin_password'));
    $usuario->id_empresa = $empresa->id_empresa;
    $usuario->role_id = 2; // ROL ADMINISTRADOR FIJO
    $usuario->save();

    // Buscar el rol 'Admin' con guard 'api'
    $role = Role::where('name', 'Admin')->where('guard_name', 'api')->first();
    if ($role) {
        $usuario->assignRole($role);
    } else {
        // Opcional: log o respuesta de error si el rol no existe
        \Log::error("El rol Admin con guard api no existe.");
    }

    return response()->json([
        "message" => 200,
        "empresa" => [
            "id" => $empresa->id_empresa,
            "nombre_empresa" => $empresa->nombre_empresa,
            "ruc_empresa" => $empresa->ruc_empresa,
            "telefono" => $empresa->telefono,
            "correo" => $empresa->correo,
            "imagen" => $empresa->imagen_empresa ? env("APP_URL") . "/storage/" . $empresa->imagen_empresa : null,
            "estado" => $empresa->estado ?? 1,
            "created_at" => $empresa->created_at?->format("Y-m-d h:i A")
        ],
        "admin" => [
            "id" => $usuario->id,
            "nombre" => $usuario->name,
            "apellido" => $usuario->surname,
            "correo" => $usuario->email,
            "empresa" => $usuario->id_empresa
        ]
    ]);
}




    public function update(Request $request, $id)
{
    //dd($request->all());
    // Busca la empresa por su ID primario
    $empresa = Empresa::findOrFail($id); // Asegúrate que $id sea id_empresa

    // Verifica si ya existe una empresa con el mismo RUC pero diferente ID
    $existe = Empresa::where("ruc_empresa", $request->ruc_empresa)
        ->where("id_empresa", "<>", $id) // Corrección aquí: usar id_empresa
        ->first();

    if ($existe) {
        return response()->json([
            "message" => 403,
            "message_text" => "El RUC ya está en uso por otra empresa"
        ]);
    }

    // Si viene un archivo de imagen, lo almacena
    if ($request->hasFile("imagen_empresa")) {
        // Borra la imagen anterior si existe
        if ($empresa->imagen_empresa) {
            Storage::delete($empresa->imagen_empresa);
        }

        // Guarda la nueva imagen
        $path = Storage::putFile("empresas", $request->file("imagen_empresa"));
        // Añade la ruta de la nueva imagen al request
        $request->merge(["imagen_empresa" => $path]);
    }

    // Actualiza los datos de la empresa
    $empresa->update($request->all());

    // Retorna la respuesta con los datos actualizados
    return response()->json([
        "message" => 200,
        "empresa" => [
            "id" => $empresa->id_empresa,
            "nombre_empresa" => $empresa->nombre_empresa,
            "ruc_empresa" => $empresa->ruc_empresa,
            "telefono" => $empresa->telefono,
            "correo" => $empresa->correo,
            "imagen" => $empresa->imagen_empresa 
                ? env("APP_URL") . "storage/" . $empresa->imagen_empresa 
                : null,
            "estado" => $empresa->estado,
            "created_at" => $empresa->created_at?->format("Y-m-d h:i A")
        ]
    ]);
}


    public function destroy($id)
    {
        $empresa = Empresa::findOrFail($id);
        if ($empresa->imagen_empresa) {
            Storage::delete($empresa->imagen_empresa);
        }

        $empresa->delete();

        return response()->json([
            "message" => 200,
            "message_text" => "Empresa eliminada correctamente"
        ]);
    }
}
