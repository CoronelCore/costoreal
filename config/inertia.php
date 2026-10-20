<?php

return [
    // This application renders in Vue; no SSR service is configured.
    'ssr' => ['enabled' => false],
    'pages' => [
        // The upload-ready package ships compiled pages only, without resources/js.
        'ensure_pages_exist' => env('APP_ENV', 'production') !== 'production',
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['vue'],
    ],
    'testing' => ['ensure_pages_exist' => true],
    'expose_shared_prop_keys' => true,
    'store_previous_url' => false,
    'history' => ['encrypt' => false],
    // Financial page data must not be recorded by local diagnostic endpoints.
    'devtools' => ['enabled' => false],
    // Financial decimals are serialized as strings by the application DTOs.
    'preserve_big_integers' => false,
];
