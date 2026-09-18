<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Realtime driver
    |--------------------------------------------------------------------------
    |
    | Auctions + matches broadcast through Laravel Broadcasting (Pusher).
    |
    */
    'driver' => env('REALTIME_DRIVER', 'pusher'),

    'broadcast_connection' => env('BROADCAST_CONNECTION', 'pusher'),

    'pusher_app_id' => env('PUSHER_APP_ID'),
    'pusher_app_key' => env('PUSHER_APP_KEY'),
    'pusher_app_secret' => env('PUSHER_APP_SECRET'),
    'pusher_app_cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
    'pusher_host' => env('PUSHER_HOST'),
    'pusher_port' => env('PUSHER_PORT', 443),
    'pusher_scheme' => env('PUSHER_SCHEME', 'https'),
];
