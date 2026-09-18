<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionTeam;
use App\Services\AuctionService;
use Illuminate\Http\Request;

class AuctionApiController extends Controller
{
    public function __construct(
        protected AuctionService $auctions
    ) {}

    public function state(Auction $auction)
    {
        return response()->json($this->auctions->overlayState($auction));
    }

    public function poll(Auction $auction)
    {
        // Kept for backwards compatibility — clients no longer poll.
        return $this->state($auction);
    }

    public function start(Auction $auction)
    {
        $auction = $this->auctions->start($auction);

        return $this->ok($auction, ['status' => $auction->status]);
    }

    public function pause(Auction $auction)
    {
        $auction = $this->auctions->pause($auction);

        return $this->ok($auction, ['status' => $auction->status]);
    }

    public function resume(Auction $auction)
    {
        $auction = $this->auctions->resume($auction);

        return $this->ok($auction, ['status' => $auction->status]);
    }

    public function nextPlayer(Auction $auction)
    {
        $player = $this->auctions->nextPlayer($auction);

        return $this->ok($auction, [
            'player' => $player ? $this->auctions->playerPayload($player) : null,
        ]);
    }

    public function bid(Request $request, Auction $auction)
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $team = AuctionTeam::where('auction_id', $auction->id)->findOrFail($data['team_id']);
        $bid = $this->auctions->placeBid($auction, $team, (int) $data['amount']);

        return $this->ok($auction, ['bid' => $bid]);
    }

    public function undoBid(Auction $auction)
    {
        $result = $this->auctions->undoLastBid($auction);

        return $this->ok($auction, ['undo' => $result]);
    }

    public function sold(Auction $auction)
    {
        $player = $this->auctions->markSold($auction);

        return $this->ok($auction, [
            'player' => $this->auctions->playerPayload($player),
        ]);
    }

    public function unsold(Auction $auction)
    {
        $player = $this->auctions->markUnsold($auction);

        return $this->ok($auction, [
            'player' => $this->auctions->playerPayload($player),
        ]);
    }

    public function complete(Auction $auction)
    {
        $auction = $this->auctions->complete($auction);

        return $this->ok($auction, ['status' => $auction->status]);
    }

    public function updatePoolPlayer(Request $request, Auction $auction, int $playerId)
    {
        $ap = $auction->players()->with('registration')->findOrFail($playerId);
        $data = $request->validate([
            'base_price' => ['sometimes', 'integer', 'min:0'],
            'category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'set_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'playing_role' => ['sometimes', 'nullable', 'string', 'max:120'],
            'batting_style' => ['sometimes', 'nullable', 'string', 'max:80'],
            'bowling_style' => ['sometimes', 'nullable', 'string', 'max:80'],
            'matches' => ['sometimes', 'nullable'],
            'runs' => ['sometimes', 'nullable'],
            'highest_score' => ['sometimes', 'nullable'],
            'wickets' => ['sometimes', 'nullable'],
        ]);

        $playerFields = ['base_price', 'category', 'set_name', 'sort_order'];
        $ap->update(collect($data)->only($playerFields)->all());

        $reg = $ap->registration;
        if ($reg) {
            $payload = $reg->data ?? [];
            foreach (['full_name', 'playing_role', 'batting_style', 'bowling_style', 'matches', 'runs', 'highest_score', 'wickets'] as $key) {
                if (array_key_exists($key, $data)) {
                    $payload[$key] = $data[$key];
                }
            }
            $computed = \App\Support\CricketStatsCalculator::compute($payload);
            foreach ($computed as $k => $v) {
                if ($v !== null) {
                    $payload[$k] = $v;
                }
            }
            $reg->update(['data' => $payload]);
            if (! empty($data['playing_role'])) {
                $ap->update(['category' => $data['playing_role']]);
            }
        }

        $state = $this->auctions->broadcast($auction->fresh());

        return response()->json([
            'ok' => true,
            'player' => $this->auctions->playerPayload($ap->fresh(['registration.files', 'soldTeam'])),
            'state' => $state,
        ]);
    }

    public function updateRules(Request $request, Auction $auction)
    {
        $data = $request->validate([
            'bid_increment' => ['sometimes', 'integer', 'min:1'],
            'bid_timer_seconds' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'default_base_price' => ['sometimes', 'integer', 'min:0'],
            'starting_purse' => ['sometimes', 'integer', 'min:0'],
            'min_squad' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'max_squad' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'custom_increments' => ['sometimes', 'array'],
            'custom_increments.*' => ['integer', 'min:1'],
            'league_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sponsor_line' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $rules = array_merge($auction->rules ?? [], $data);
        $auction->update(['rules' => $rules]);
        if (isset($data['min_squad']) || isset($data['max_squad'])) {
            $this->auctions->applySquadRules($auction->fresh());
        }
        $state = $this->auctions->broadcast($auction->fresh());

        return response()->json(['ok' => true, 'rules' => $rules, 'state' => $state]);
    }

    public function setBroadcast(Request $request, Auction $auction)
    {
        $data = $request->validate([
            'view' => ['required', 'string', 'in:player,split,team,teams,idle'],
            'team_id' => ['nullable', 'integer'],
        ]);

        $live = $auction->live_state ?? [];
        $auction->update([
            'live_state' => array_merge($live, [
                'broadcastView' => $data['view'],
                'broadcastTeamId' => $data['team_id'] ?? null,
            ]),
        ]);
        $state = $this->auctions->broadcast($auction->fresh());

        return response()->json([
            'ok' => true,
            'broadcastView' => $data['view'],
            'broadcastTeamId' => $data['team_id'] ?? null,
            'state' => $state,
        ]);
    }

    public function updateTeam(Request $request, Auction $auction, AuctionTeam $team)
    {
        abort_unless($team->auction_id === $auction->id, 404);
        abort_if($auction->status === 'completed', 422, 'Cannot edit teams after the auction is completed.');

        $data = $request->validate([
            'starting_purse' => ['nullable', 'integer', 'min:0'],
            'remaining' => ['nullable', 'integer', 'min:0'],
            'owner' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
        ]);

        if (array_key_exists('owner', $data) || array_key_exists('city', $data)) {
            $fields = collect($data)->only(['owner', 'city'])->all();
            if (array_key_exists('city', $fields)) {
                $fields['manager'] = $fields['city'];
                if (! \Illuminate\Support\Facades\Schema::hasColumn('auction_teams', 'city')) {
                    unset($fields['city']);
                }
            }
            $team->update($fields);
        }

        if (array_key_exists('starting_purse', $data) || array_key_exists('remaining', $data)) {
            $this->auctions->updateTeamPurse(
                $team->fresh(),
                isset($data['starting_purse']) ? (int) $data['starting_purse'] : null,
                isset($data['remaining']) ? (int) $data['remaining'] : null
            );
        }

        $state = $this->auctions->broadcast($auction->fresh());

        return response()->json([
            'ok' => true,
            'team' => $team->fresh(),
            'state' => $state,
        ]);
    }

    public function reorderQueue(Request $request, Auction $auction)
    {
        abort_if($auction->status === 'completed', 422, 'Cannot reorder after the auction is completed.');

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $this->auctions->reorderQueue($auction, $data['order']);

        return $this->ok($auction->fresh());
    }

    protected function ok(Auction $auction, array $extra = [])
    {
        return response()->json(array_merge([
            'ok' => true,
            'state' => $this->auctions->overlayState($auction->fresh([
                'tournament',
                'currentPlayer.registration.files',
                'teams',
                'players.registration.files',
                'players.soldTeam',
            ])),
        ], $extra));
    }
}
