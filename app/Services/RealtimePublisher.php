<?php

namespace App\Services;

use App\Events\RealtimeRoomUpdated;
use Illuminate\Support\Facades\Log;

/**
 * Publishes match + auction live updates via Laravel Broadcasting (Pusher).
 */
class RealtimePublisher
{
    public function publish(string $room, array $state, int $version, ?array $animation = null): void
    {
        if (! $this->configured()) {
            return;
        }

        $state = $this->fitState($state, $version, $animation);

        try {
            broadcast(new RealtimeRoomUpdated(
                room: $room,
                state: $state,
                version: $version,
                animation: $animation,
            ));
        } catch (\Throwable $e) {
            Log::warning('Realtime broadcast failed', [
                'room' => $room,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pusher rejects events over 10KB. Drop heavy lists first so bids still go out.
     * Clients merge omitted fields from the last full HTTP hydrate.
     */
    protected function fitState(array $state, int $version, ?array $animation): array
    {
        $limit = 9000;
        $candidates = [];

        $candidates[] = $state;

        $noQueue = $state;
        unset($noQueue['queue']);
        $noQueue['_partial'] = true;
        $candidates[] = $noQueue;

        $noLastEvent = $noQueue;
        unset($noLastEvent['lastEvent']);
        $candidates[] = $noLastEvent;

        $compactTeams = $noLastEvent;
        if (isset($compactTeams['teams']) && is_array($compactTeams['teams'])) {
            $compactTeams['teams'] = array_map(function ($team) {
                if (is_array($team)) {
                    unset($team['squad']);
                }

                return $team;
            }, $compactTeams['teams']);
        }
        $candidates[] = $compactTeams;

        foreach ($candidates as $candidate) {
            if ($this->envelopeBytes($candidate, $version, $animation) <= $limit) {
                if (! empty($candidate['_partial'])) {
                    Log::info('Realtime payload trimmed to fit Pusher limit', [
                        'bytes' => $this->envelopeBytes($candidate, $version, $animation),
                        'has_queue' => array_key_exists('queue', $candidate),
                        'has_squad' => isset($candidate['teams'][0]['squad']),
                    ]);
                }

                return $candidate;
            }
        }

        Log::warning('Realtime payload still large after trim', [
            'bytes' => $this->envelopeBytes($compactTeams, $version, $animation),
        ]);

        return $compactTeams;
    }

    protected function envelopeBytes(array $state, int $version, ?array $animation): int
    {
        $payload = [
            'type' => $animation ? 'animation' : 'state',
            'room' => 'live-room',
            'version' => $version,
            'state' => $state,
            'at' => now()->toIso8601String(),
        ];
        if ($animation) {
            $payload['animation'] = $animation['animation'] ?? $animation['type'] ?? 'custom';
            $payload['payload'] = $animation['payload'] ?? [];
            $payload['id'] = $animation['id'] ?? 'anim';
        }

        return strlen((string) json_encode($payload));
    }

    public function configured(): bool
    {
        $connection = (string) config('broadcasting.default');
        if ($connection === 'null' || $connection === '') {
            return false;
        }

        if ($connection === 'pusher') {
            return filled(config('broadcasting.connections.pusher.key'))
                && filled(config('broadcasting.connections.pusher.secret'))
                && filled(config('broadcasting.connections.pusher.app_id'));
        }

        return true;
    }

    public function health(): array
    {
        $connection = (string) config('broadcasting.default', 'null');
        $pusher = config('broadcasting.connections.pusher', []);
        $configured = $this->configured();

        return [
            'ok' => $configured && $connection === 'pusher',
            'driver' => $connection,
            'url' => $this->clientHost(),
            'configured' => $configured,
            'cluster' => $pusher['options']['cluster'] ?? null,
            'app_id' => $pusher['app_id'] ?? null,
            'error' => $configured ? null : 'Set PUSHER_APP_ID, PUSHER_APP_KEY, and PUSHER_APP_SECRET in .env',
        ];
    }

    protected function clientHost(): string
    {
        $options = config('broadcasting.connections.pusher.options', []);
        $scheme = $options['scheme'] ?? 'https';
        $host = $options['host'] ?? ('api-'.($options['cluster'] ?? 'mt1').'.pusher.com');
        $port = $options['port'] ?? 443;

        return "{$scheme}://{$host}:{$port}";
    }
}
