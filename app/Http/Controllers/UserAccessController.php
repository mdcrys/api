<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\Configuration\Sucursale;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;



class UserAccessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
 public function index(Request $request)
{
    // 1. Autorización: Verifica si el usuario actual puede ver la lista de usuarios.
    $this->authorize("viewAny", User::class);

    $search = $request->get("search");
    $user = auth()->user();

    // 2. Inicio de la Consulta y Carga Ansiosa (Eager Loading)
    // Cargamos los roles y sus permisos para evitar problemas de N+1 queries.
    $query = User::query()->with('roles.permissions');

    // 3. Filtrado por Empresa (Excluyendo al Superadministrador)
    if ($user->id !== 1) {
        $query->where('id_empresa', $user->id_empresa);
    }

    // 4. Aplicación de la Búsqueda
    $users = $query->where(function($q) use ($search) {
            $q->where("name", "like", "%".$search."%")
              ->orWhere("surname", "like", "%".$search."%")
              ->orWhere("email", "like", "%".$search."%");
        })
        ->orderBy("id", "desc")
        ->paginate(25);

    // 5. Respuesta JSON y Formato
    return response()->json([
        "total" => $users->total(),
        "users" => $users->map(function($user) {
            
            // 💡 Lógica para extraer y consolidar los permisos
            if ($user->id === 1) {
                // Si es el Superadministrador (ID 1), asignamos un marcador de acceso total.
                // Podrías cambiar 'super_admin_access' por la lista real de todos los permisos si es necesario.
                $permissions = collect(['super_admin_access']);
            } else {
                // Para el resto de usuarios, consolidamos los permisos de todos sus roles.
                $permissions = $user->roles
                    ->pluck('permissions') // Obtiene una colección de colecciones de permisos
                    ->flatten()           // Aplana la colección a un solo nivel
                    ->pluck('name')       // Extrae solo el nombre (string) del permiso
                    ->unique()            // Elimina duplicados
                    ->values();           // Reindexa la matriz
            }

            return [
                "id" => $user->id,
                "name" => $user->name,
                "email" => $user->email,
                "surname" => $user->surname,
                "full_name" => $user->name.' '.$user->surname,
                "phone" => $user->phone,
                "role_id" => $user->role_id,
                "role" => $user->role,          // Asumo que 'role' es una relación cargada o un atributo
                "roles" => $user->roles,        // Los roles cargados con sus permisos
                "sucursale_id" => $user->sucursale_id,
                "sucursale" => $user->sucursale, // Asumo que 'sucursale' es una relación cargada
                "type_document" => $user->type_document,
                "n_document" => $user->n_document,
                "gender" => $user->gender,
                "avatar" => $user->avatar 
                    ? env("APP_URL")."storage/".$user->avatar 
                    : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png',
                "created_format_at" => $user->created_at->format("Y-m-d h:i A"),
                "id_empresa" => $user->id_empresa,
                
                // ✅ Permisos finales consolidados
                "permissions" => $permissions,
            ];
        }),
    ]);
}



    public function config(){
        // 1. Obtener el objeto completo del usuario autenticado
        $user = Auth::user(); 
        
        // Manejo de seguridad
        if (!$user) {
            return response()->json(["roles" => [], "sucursales" => []], 401);
        }
        
        // 2. Iniciar la consulta de Roles
        $roles_query = Role::query();

        // 3. LÓGICA DE FILTRADO: Si el usuario NO es Super-Admin (asumimos role_id 1)
        if ($user->role_id != 1) {
            
            // Si el usuario tiene un id_empresa asignado
            if ($user->id_empresa) {
                // FILTRO CLAVE: Solo trae roles que coincidan con la id_empresa del usuario
                $roles_query->where('id_empresa', $user->id_empresa);
            }
            
            // Excluir el rol de Super-Admin (ID 1)
            $roles_query->where('id', '!=', 1); 
        }
        
        // 4. Obtener los roles finales
        $roles = $roles_query->get();

        // 5. Devolver la respuesta
        return response()->json([
            "roles" => $roles, // Lista de roles ya filtrada por empresa/jerarquía
            "sucursales" => Sucursale::where("state",1)->get(),
        ]);
        dd([
            "roles" => $roles,
            "sucursales" => Sucursale::where("state",1)->get(),
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         //dd($request->all());
        $this->authorize("create", User::class);

        // VALIDAR QUE EL USUARIO NO EXISTA
        $USER_EXITS = User::where("email", $request->email)->first();
        if ($USER_EXITS) {
            return response()->json([
                "message" => 403,
                "message_text" => "EL USUARIO YA EXISTE"
            ]);
        }

        // SUBIR IMAGEN
        if ($request->hasFile("imagen")) {
            $path = Storage::putFile("users", $request->file("imagen"));
            $request->request->add(["avatar" => $path]);
        }

        // ENCRIPTAR PASSWORD
        if ($request->password) {
            $request->request->add(["password" => bcrypt($request->password)]);
        }

        /* -------------------------------------------------------------
        1️⃣ SI role_id ES "usuario", CREAR UN ROL DINÁMICO
        --------------------------------------------------------------*/
        if ($request->role_id === "usuario") {

            // NOMBRE DEL ROL = Usuario - Nombre Apellido
            $roleName = "Usuario - " . $request->name . " " . $request->surname;

              // Crear el rol con id_empresa
                $role = Role::create([
                    "name" => $roleName,
                    'guard_name' => 'api',
                    "id_empresa" => $request->id_empresa   // <-- AQUÍ!
                ]);

            // Asignar permisos enviados
            if ($request->permissions && is_array($request->permissions)) {
                foreach ($request->permissions as $permiso) {
                    // si no existe, lo creamos
                    Permission::firstOrCreate(["name" => $permiso]);
                    $role->givePermissionTo($permiso);
                }
            }

            // Sobrescribir role_id con el ID real del rol creado
            $request->request->add(["role_id" => $role->id]);
        }

        /* -------------------------------------------------------------
        2️⃣ CREAR USUARIO NORMALMENTE
        --------------------------------------------------------------*/
        $role = Role::findOrFail($request->role_id);

        $user = User::create($request->all());

        // asignar el rol al usuario
        $user->assignRole($role);

        return response()->json([
            "message" => 200,
            "user" => [
                "id" => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                "surname" => $user->surname,
                "full_name" => $user->name.' '.$user->surname,
                "phone" =>  $user->phone,
                "role_id" => $user->role_id,
                "roles" => $user->roles,
                "sucursale_id" => $user->sucursale_id,
                "avatar" => $user->avatar ? env("APP_URL")."storage/".$user->avatar : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png',
                "created_format_at" => $user->created_at->format("Y-m-d h:i A"),
            ]
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
{
    $this->authorize("update", User::class);

    // Verificar email duplicado
    $USER_EXITS = User::where("email", $request->email)
                      ->where("id", "<>", $id)
                      ->first();

    if ($USER_EXITS) {
        return response()->json([
            "message" => 403,
            "message_text" => "EL USUARIO YA EXISTE"
        ]);
    }

    $user = User::findOrFail($id);

    // Subir imagen
    if ($request->hasFile("imagen")) {

        if ($user->avatar) {
            Storage::delete($user->avatar);
        }

        $path = Storage::putFile("users", $request->file("imagen"));
        $request->merge(["avatar" => $path]);
    }

    // Encriptar password si viene
    if ($request->password) {
        $request->merge(["password" => bcrypt($request->password)]);
    }

    // =============================
    // 🔵 CAMBIO DE ROL DEL USUARIO
    // =============================
    $role_new = null;

    if ($request->role_id != $user->role_id) {

        // Rol viejo
        $role_old = Role::findOrFail($user->role_id);
        $user->removeRole($role_old);

        // Nuevo rol
        $role_new = Role::findOrFail($request->role_id);
        $user->assignRole($role_new);

        // Actualizar en tabla users
        $request->merge(["role_id" => $role_new->id]);
    } else {
        // Mantener rol actual
        $role_new = Role::findOrFail($user->role_id);
    }

    // ============================================================
    // 🔵 ACTUALIZAR PERMISOS DEL ROL (igual que la creación empresa)
    // ============================================================
    if ($request->has('permissions')) {

        $permisos = json_decode($request->permissions, true) ?? [];

        foreach ($permisos as $permiso) {

            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'api'
            ]);
        }

        // Sincronizar permisos con el rol
        $role_new->syncPermissions($permisos);
    }

    // Actualizar usuario
    $user->update($request->all());

    // Obtener el rol actualizado
    $current_role = $user->roles()->first();

    return response()->json([
        "message" => 200,
        "user" => [
            "id" => $user->id,
            "name" => $user->name,
            "surname" => $user->surname,
            "full_name" => $user->name . " " . $user->surname,
            "email" => $user->email,
            "phone" => $user->phone,

            "role_id" => $user->role_id,
            "role_name" => $current_role ? $current_role->name : null,
            "role" => $current_role,

            "sucursale_id" => $user->sucursale_id,
            "sucursale" => $user->sucursale,

            "type_document" => $user->type_document,
            "n_document" => $user->n_document,
            "gender" => $user->gender,

            "avatar" => $user->avatar
                ? env("APP_URL")."storage/".$user->avatar
                : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png',

            "created_format_at" => $user->created_at->format("Y-m-d h:i A"),
        ]
    ]);
}


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize("delete",User::class);
        $user = User::findOrFail($id);
        if($user->avatar){
            Storage::delete($user->avatar);
        }
        $user->delete();
        return response()->json([
            "message" => 200,
        ]);
    }
}
