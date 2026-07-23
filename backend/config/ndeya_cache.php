<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration Cache Redis - OPTIMISÉ POUR PRODUCTION
    |--------------------------------------------------------------------------
    | Configuration optimisée pour supporter 2000+ clients simultanés
    */

    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('CACHE_PREFIX', 'ndeya_shop'),

    /*
    |--------------------------------------------------------------------------
    | Cache Tags - Pour invalidation sélective
    |--------------------------------------------------------------------------
    */
    'tags' => [
        'products' => 'products',
        'categories' => 'categories',
        'home' => 'home',
        'navigation' => 'navigation',
        'cart' => 'cart',
    ],

    /*
    |--------------------------------------------------------------------------
    | Durées de cache (en secondes)
    |--------------------------------------------------------------------------
    */
    'ttl' => [
        'products_list' => env('CACHE_PRODUCTS_LIST_TTL', 3600), // 1 heure
        'product_detail' => env('CACHE_PRODUCT_DETAIL_TTL', 7200), // 2 heures
        'categories' => env('CACHE_CATEGORIES_TTL', 86400), // 24 heures
        'home_data' => env('CACHE_HOME_DATA_TTL', 1800), // 30 minutes
        'navigation' => env('CACHE_NAVIGATION_TTL', 43200), // 12 heures
        'config' => env('CACHE_CONFIG_TTL', 86400), // 24 heures
        'cart' => env('CACHE_CART_TTL', 3600), // 1 heure
    ],
];
