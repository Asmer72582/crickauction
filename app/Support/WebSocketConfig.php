<?php

namespace App\Support;

use Illuminate\Http\Request;

class WebSocketConfig
{
    /**
     * Public Pusher config for browser Echo clients.
     */
    public static function pusherClientConfig(?Request $request = null): array
    {
        $pusher = config('broadcasting.connections.pusher', []);
        $options = $pusher['options'] ?? [];
        $customHost = $options['host'] ?? null;
        $isCustom = $customHost && ! str_contains((string) $customHost, 'pusher.com');

        return [
            'key' => $pusher['key'] ?? '',
            'cluster' => $options['cluster'] ?? 'mt1',
            'wsHost' => $isCustom ? $customHost : null,
            'wsPort' => $isCustom ? (int) ($options['port'] ?? 80) : null,
            'wssPort' => $isCustom ? (int) ($options['port'] ?? 443) : null,
            'forceTLS' => ($options['scheme'] ?? 'https') === 'https',
            'authEndpoint' => '/broadcasting/auth',
        ];
    }

    /** @deprecated Use pusherClientConfig() */
    public static function clientUrl(?Request $request = null): string
    {
        $cfg = self::pusherClientConfig($request);

        return $cfg['key'] ? 'pusher://'.$cfg['cluster'] : '';
    }

    public static function credentials(?Request $request = null): array
    {
        $pusher = config('broadcasting.connections.pusher', []);
        $options = $pusher['options'] ?? [];

        return [
            'REALTIME_DRIVER' => 'pusher',
            'BROADCAST_CONNECTION' => config('broadcasting.default'),
            'PUSHER_APP_ID' => $pusher['app_id'] ?: '',
            'PUSHER_APP_KEY' => $pusher['key'] ?: '',
            'PUSHER_APP_SECRET' => ($pusher['secret'] ?? null) ? '••••••••' : '',
            'PUSHER_APP_CLUSTER' => $options['cluster'] ?? 'mt1',
            'PUSHER_HOST' => $options['host'] ?? '',
            'PUSHER_PORT' => $options['port'] ?? '',
            'PUSHER_SCHEME' => $options['scheme'] ?? 'https',
            'CHANNEL_PATTERN' => 'live.{room}',
            'EVENT' => 'realtime',
        ];
    }
}
