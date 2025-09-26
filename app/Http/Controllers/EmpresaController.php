<?php

namespace App\Http\Controllers;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use App\Models\Empresa;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Spatie\Permission\Models\Permission;

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
    //dd($request->all());
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
    $usuario->save();

    // Limpiar cache de Spatie
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Crear rol único por empresa: Admin-{nombre_empresa}
    $rolNombre = "Admin-" . $empresa->nombre_empresa;

    $role = \Spatie\Permission\Models\Role::where('name', $rolNombre)
        ->where('guard_name', 'api')
        ->where('id_empresa', $empresa->id_empresa)
        ->first();

    if (!$role) {
        $role = \Spatie\Permission\Models\Role::create([
            'name' => $rolNombre,
            'guard_name' => 'api',
            'id_empresa' => $empresa->id_empresa
        ]);
    }

    // Lista de permisos recibidos desde la request
    $permisos = json_decode($request->input('permisos'), true) ?? [];

    // Filtrar solo los permisos que realmente existen en la tabla permissions
    $permisosExistentes = \Spatie\Permission\Models\Permission::whereIn('name', $permisos)
        ->where('guard_name', 'api')
        ->pluck('name')
        ->toArray();

    // Asignar solo los permisos existentes al rol
    if (!empty($permisosExistentes)) {
        $role->syncPermissions($permisosExistentes);
    }

    // Asignar rol al usuario
    $usuario->assignRole($role);

    // Mostrar los permisos asignados al usuario para debug
    // Guardar role_id en la tabla users
    $usuario->role_id = $role->id;
    $usuario->save();


    // Respuesta JSON
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
            "empresa" => $usuario->id_empresa,
            "rol" => $rolNombre,
            "permisos" => $permisosExistentes
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
