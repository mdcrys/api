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

        $empresas = Empresa::with(['usuarios.roles', 'usuarios.permissions'])
            ->where("nombre_empresa", "like", "%" . $search . "%")
            ->orderBy("id_empresa", "desc")
            ->paginate(25);

        return response()->json([
            "total" => $empresas->total(),
            "empresas" => $empresas->map(function ($empresa) {

                // Buscar usuario con rol que empieza en "Admin-"
                $admin = $empresa->usuarios->first(function ($user) {
                    return $user->roles->contains(function ($role) {
                        return str_starts_with($role->name, 'Admin-');
                    });
                });

                return [
                    "id" => $empresa->id_empresa,
                    "nombre_empresa" => $empresa->nombre_empresa,
                    "ruc_empresa" => $empresa->ruc_empresa,
                    "telefono" => $empresa->telefono,
                    "correo" => $empresa->correo,
                    "direccion" => $empresa->direccion,
                    "imagen" => $empresa->imagen_empresa 
                        ? env("APP_URL") . "storage/" . $empresa->imagen_empresa 
                        : null,
                    "ruta_firma" => $empresa->ruta_firma,
                    "contrasena_firma" => $empresa->contrasena_firma,
                    "fecha_subida_firma" => $empresa->fecha_subida_firma,
                    "fecha_expiracion_firma" => $empresa->fecha_expiracion_firma,
                    "fecha_ultima_firma" => $empresa->fecha_ultima_firma,
                    "estado" => $empresa->estado,
                    "created_at" => $empresa->created_at?->format("Y-m-d h:i A"),

                    // Datos del Admin
                    "admin" => $admin ? [
                        "id" => $admin->id,
                        "nombre" => $admin->name,
                        "apellido" => $admin->surname,
                        "email" => $admin->email,
                        "password" => $admin->password,
                        "roles" => $admin->roles->pluck('name'), // Roles del admin
                        "permisos" => $admin->getAllPermissions()->pluck('name') // Permisos completos del admin
                    ] : null
                ];
            }),
        ]);
    }


    public function store(Request $request)
    {
        // 1️⃣ Validar si el RUC ya existe
        if ($request->has('ruc_empresa')) {
            $existe = Empresa::where("ruc_empresa", $request->ruc_empresa)->first();
            if ($existe) {
                return response()->json([
                    "message" => 403,
                    "message_text" => "El RUC ya existe"
                ]);
            }
        }

        // 2️⃣ Procesar logo
        $imagenPath = null;
        if ($request->hasFile("logo")) {
            $imagenPath = $request->file("logo")->store("empresas", "public");
        }

        // 3️⃣ Crear empresa
        $empresa = Empresa::create([
            'nombre_empresa' => $request->input('nombre_empresa'),
            'ruc_empresa' => $request->input('ruc_empresa', ''),
            'telefono' => $request->input('telefono', ''),
            'correo' => $request->input('correo', ''),
            'direccion' => $request->input('direccion', ''),
            'imagen_empresa' => $imagenPath,
            'estado' => $request->input('estado', 1),
        ]);

        // 4️⃣ Guardar firma digital (opcional)
        if ($request->hasFile('firma')) {
            $carpetaDestino = storage_path('app/public/firma/' . $empresa->id_empresa);
            if (!file_exists($carpetaDestino)) mkdir($carpetaDestino, 0777, true);

            $nombreArchivo = $request->file('firma')->getClientOriginalName();
            $request->file('firma')->move($carpetaDestino, $nombreArchivo);

            $empresa->update([
                'ruta_firma' => "firma/{$empresa->id_empresa}/{$nombreArchivo}",
                'contrasena_firma' => $request->input('contrasena_firma', null),
                'fecha_subida_firma' => now(),
                'fecha_expiracion_firma' => $request->input('fecha_expiracion_firma', null),
            ]);
        }

        // 5️⃣ Crear usuario administrador
        $usuario = \App\Models\User::create([
            'name' => $request->input('admin_nombre'),
            'surname' => $request->input('admin_apellido'),
            'email' => $request->input('admin_correo'),
            'password' => bcrypt($request->input('admin_password')),
            'id_empresa' => $empresa->id_empresa
        ]);

        // 6️⃣ Limpiar cache de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 7️⃣ Crear rol único de la empresa
        $rolNombre = "Admin-" . $empresa->nombre_empresa;
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => $rolNombre,
            'guard_name' => 'api',
            'id_empresa' => $empresa->id_empresa
        ]);

        // 8️⃣ Asignar permisos al rol
        $permisos = json_decode($request->input('permisos'), true) ?? [];
        foreach ($permisos as $permiso) {
            // Crear permiso si no existe
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'api'
            ]);
        }

        // Sincronizar permisos con el rol
        $role->syncPermissions($permisos);

        // 9️⃣ Asignar rol al usuario
        $usuario->assignRole($role);
        $usuario->role_id = $role->id;
        $usuario->save();

        // 10️⃣ Respuesta
        return response()->json([
            "message" => 200,
            "empresa" => [
                "id" => $empresa->id_empresa,
                "nombre_empresa" => $empresa->nombre_empresa,
                "ruc_empresa" => $empresa->ruc_empresa,
                "telefono" => $empresa->telefono,
                "correo" => $empresa->correo,
                "imagen" => $empresa->imagen_empresa ? env("APP_URL") . "/storage/" . $empresa->imagen_empresa : null,
                "firma" => $empresa->ruta_firma ? env("APP_URL") . "/storage/" . $empresa->ruta_firma : null,
                "estado" => $empresa->estado ?? 1,
            ]
        ]);
    }



    public function update(Request $request, $id)
    {
        // Buscar empresa
        $empresa = Empresa::findOrFail($id);

        // Validar RUC único excepto para esta empresa
        $existe = Empresa::where("ruc_empresa", $request->ruc_empresa)
            ->where("id_empresa", "<>", $id)
            ->first();

        if ($existe) {
            return response()->json([
                "message" => 403,
                "message_text" => "El RUC ya está en uso por otra empresa"
            ]);
        }

        /* ============================
        1️⃣ IMAGEN DE LA EMPRESA
        ============================ */
        if ($request->hasFile("imagen_empresa")) {

            // Eliminar imagen previa si existe
            if ($empresa->imagen_empresa) {
                Storage::delete($empresa->imagen_empresa);
            }

            // Guardar nueva
            $path = Storage::putFile("empresas", $request->file("imagen_empresa"));

            // Inyectar al request
            $request->merge(["imagen_empresa" => $path]);
        }

        /* ============================
        2️⃣ FIRMA DIGITAL (P12)
        ============================ */
        if ($request->hasFile('firma_digital')) {

            // Crear carpeta única por empresa
            $carpeta = "public/firma/" . $empresa->id_empresa;

            if (!Storage::exists($carpeta)) {
                Storage::makeDirectory($carpeta);
            }

            // Nombre original del archivo
            $nombre = $request->file('firma_digital')->getClientOriginalName();

            // Guardar archivo
            $rutaCompleta = $request->file('firma_digital')
                ->storeAs($carpeta, $nombre);

            // Actualizar campos de firma digital
            $empresa->ruta_firma = "firma/{$empresa->id_empresa}/{$nombre}";
            $empresa->contrasena_firma = $request->contrasena_firma;
            $empresa->fecha_expiracion_firma = $request->fecha_expiracion_firma;
            $empresa->fecha_subida_firma = now();
        }

        /* ============================
        3️⃣ ACTUALIZAR EMPRESA
        ============================ */
        $empresa->update($request->except("firma_digital"));

        $empresa->save();

        /* ============================
        4️⃣ RESPUESTA
        ============================ */
        return response()->json([
            "message" => 200,
            "empresa" => [
                "id" => $empresa->id_empresa,
                "nombre_empresa" => $empresa->nombre_empresa,
                "ruc_empresa" => $empresa->ruc_empresa,
                "telefono" => $empresa->telefono,
                "correo" => $empresa->correo,
                "imagen" => $empresa->imagen_empresa 
                    ? asset("storage/" . $empresa->imagen_empresa)
                    : null,
                "firma" => $empresa->ruta_firma 
                    ? asset("storage/" . $empresa->ruta_firma)
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

    public function show($id)
    {
        try {
            $empresa = Empresa::find($id);

            if (!$empresa) {
                return response()->json(['message' => 'Empresa no encontrada'], 404);
            }

            return response()->json($empresa, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener la empresa', 'details' => $e->getMessage()], 500);
        }
    }

}
