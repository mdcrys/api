<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para permitir que tu frontend Angular pueda consumir recursos
    | (como PDFs) desde tu backend Laravel sin problemas de CORS.
    |
    */

    // Rutas a las que se aplicará CORS
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'storage/*',          // tu carpeta storage
        'stream_progreso_pdf/*', // si tienes alguna ruta para streaming de PDFs
        'getImagenesPDF/*', // si tienes alguna ruta para streaming de PDFs
        
    ],

    // Métodos HTTP permitidos
    'allowed_methods' => ['*'],

    // Orígenes permitidos (puedes restringir a tu frontend específico)
    'allowed_origins' => [
   //'http://localhost:4200',       // desarrollo
   'https://api-doc.sisgesdoc.com',       // producción
    'https://doc.sisgesdoc.com' // producción
],

    // Patrones de origen (si necesitas regex)
    'allowed_origins_patterns' => [],

    // Cabeceras permitidas
    'allowed_headers' => ['*'],

    // Cabeceras que pueden ser expuestas al frontend
    'exposed_headers' => [],

    // Tiempo máximo que el navegador cachea la respuesta CORS
    'max_age' => 0,

    // Si soporta credenciales (cookies, auth headers)
    'supports_credentials' => false,
];
