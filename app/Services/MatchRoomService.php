<?php

namespace App\Services;

use App\Models\MatchRoom;

class MatchRoomService
{
    public function __construct(
        protected CricketScoringService $scoring,
        protected RealtimePublisher $realtime
    ) {}

    public function getOrCreate(string $roomId): MatchRoom
    {
        $roomId = trim($roomId) ?: 'match1';

        return MatchRoom::firstOrCreate(
            ['room_id' => $roomId],
            [
                'state' => CricketScoringService::defaultState(),
                'event_queue' => [],
                'version' => 0,
            ]
        );
    }

    public function saveState(MatchRoom $room, array $state, ?array $animation = null, bool $skipBracketHook = false): MatchRoom
    {
        $state = $this->scoring->ensureDerived($state);
        $room->state = $state;
        $room->version = ($room->version ?? 0) + 1;

        $queued = null;
        if ($animation) {
            $queue = $room->event_queue ?? [];
            $queued = array_merge($animation, ['id' => uniqid('anim_', true), 'at' => now()->toIso8601String()]);
            $queue[] = $queued;
            $room->event_queue = array_slice($queue, -20);
        }

        $room->save();
        $fresh = $room->fresh();

        $this->realtime->publish(
            $fresh->room_id,
            $fresh->state ?? $state,
            (int) $fresh->version,
            $queued
        );

        if (! $skipBracketHook) {
            try {
                app(KnockoutBracketService::class)->onMatchRoomCompleted($fresh);
            } catch (\Throwable) {
                // Bracket advancement must not break scoring saves
            }
        }

        return $fresh;
    }

    /**
     * Persist + publish without running knockout advancement (seed / overlay sync).
     */
    public function saveStateQuiet(MatchRoom $room, array $state, ?array $animation = null): MatchRoom
    {
        return $this->saveState($room, $state, $animation, true);
    }

    public function drainEvents(MatchRoom $room, ?string $afterId = null): array
    {
        $queue = $room->event_queue ?? [];
        if ($afterId) {
            $found = false;
            $out = [];
            foreach ($queue as $ev) {
                if ($found) {
                    $out[] = $ev;
                }
                if (($ev['id'] ?? '') === $afterId) {
                    $found = true;
                }
            }

            return $out;
        }

        return $queue;
    }

    public function clearEvents(MatchRoom $room): void
    {
        $room->event_queue = [];
        $room->save();
    }
}
