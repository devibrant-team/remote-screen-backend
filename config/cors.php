<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
       'http://localhost:5173',       // React or Vite dev server
       'http://localhost:5174',       // React or Vite dev server
      'http://192.168.10.107:5173', // if you're using LAN
       'http://localhost:5173',
        'http://127.0.0.1:5173',

    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // Use true if using cookies/session auth (e.g., Sanctum)

];


