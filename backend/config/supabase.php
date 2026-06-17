<?php

return [
    'url'              => env('SUPABASE_URL'),
    'anon_key'         => env('SUPABASE_ANON_KEY'),
    'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY'),
    'jwt_secret'       => env('SUPABASE_JWT_SECRET'),
    'storage' => [
        'fdx_bucket'     => env('SUPABASE_STORAGE_FDX_BUCKET', 'fdx-files'),
        'exports_bucket' => env('SUPABASE_STORAGE_EXPORTS_BUCKET', 'exports'),
    ],
];
