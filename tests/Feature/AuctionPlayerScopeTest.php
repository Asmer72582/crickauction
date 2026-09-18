<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\PlayerRegistration;
use App\Models\User;
use App\Services\AuctionService;
use App\Services\RegistrationFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionPlayerScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_auction_gets_its_own_registration_form_and_token(): void
    {
        [$auctionA, $auctionB] = $this->twoStandaloneAuctions();

        $this->assertNotEquals(
            $auctionA->registration_form_id,
            $auctionB->registration_form_id
        );
        $this->assertNotEquals(
            $auctionA->registrationForm->public_token,
            $auctionB->registrationForm->public_token
        );
        $this->assertSame($auctionA->id, $auctionA->registrationForm->auction_id);
        $this->assertSame($auctionB->id, $auctionB->registrationForm->auction_id);
    }

    public function test_public_registration_lands_only_on_that_auction(): void
    {
        [$auctionA, $auctionB] = $this->twoStandaloneAuctions();

        $this->post('/registration/'.$auctionA->registrationForm->public_token, [
            'full_name' => 'Only Alpha',
            'mobile' => '9999999999',
            'playing_role' => ['Batter'],
            'terms' => '1',
        ])->assertRedirect();

        $this->assertSame(1, $auctionA->registrationForm->registrations()->count());
        $this->assertSame(0, $auctionB->registrationForm->registrations()->count());
        $this->assertSame(
            'Only Alpha',
            $auctionA->registrationForm->registrations()->first()?->displayName()
        );
        $this->assertFalse(
            PlayerRegistration::where('registration_form_id', $auctionB->registration_form_id)->exists()
        );
    }

    public function test_admin_added_player_stays_on_that_auction(): void
    {
        $user = User::factory()->create();
        [$auctionA, $auctionB] = $this->twoStandaloneAuctions();

        $this->actingAs($user)->post(route('auctions.players.store', $auctionB), [
            'name' => 'Desk Player',
            'roles' => ['Bowler'],
            'base_price' => 20000,
            'add_to_pool' => 1,
        ])->assertRedirect();

        $this->assertSame(0, $auctionA->registrationForm->registrations()->count());
        $this->assertSame(1, $auctionB->registrationForm->registrations()->count());
        $this->assertSame(0, $auctionA->players()->count());
        $this->assertSame(1, $auctionB->players()->count());
        $this->assertSame('Desk Player', $auctionB->players()->first()?->registration?->displayName());
    }

    public function test_shared_forms_are_split_onto_each_auction(): void
    {
        [$auctionA, $auctionB] = $this->twoStandaloneAuctions();
        $shared = $auctionA->registrationForm;

        PlayerRegistration::create([
            'registration_form_id' => $shared->id,
            'form_version' => $shared->current_version,
            'registration_code' => 'SH-TEST-00001',
            'data' => ['full_name' => 'Shared Player', 'playing_role' => 'Batter'],
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        $reg = $shared->registrations()->first();
        $auctionA->players()->create([
            'player_registration_id' => $reg->id,
            'category' => 'Batter',
            'base_price' => 20000,
            'sort_order' => 1,
            'status' => 'pool',
        ]);
        $auctionB->update(['registration_form_id' => $shared->id]);
        $auctionB->players()->create([
            'player_registration_id' => $reg->id,
            'category' => 'Batter',
            'base_price' => 20000,
            'sort_order' => 1,
            'status' => 'pool',
        ]);
        $shared->update(['auction_id' => null]);

        app(RegistrationFormService::class)->assignExclusiveAuctionForms();

        $auctionA->refresh()->load('registrationForm');
        $auctionB->refresh()->load(['registrationForm', 'players.registration']);

        $this->assertNotEquals($auctionA->registration_form_id, $auctionB->registration_form_id);
        $this->assertSame($auctionA->id, $auctionA->registrationForm->auction_id);
        $this->assertSame($auctionB->id, $auctionB->registrationForm->auction_id);
        $this->assertNotEquals(
            $auctionA->players()->first()->player_registration_id,
            $auctionB->players()->first()->player_registration_id
        );
        $this->assertSame('Shared Player', $auctionB->players()->first()->registration->displayName());
        $this->assertSame($auctionB->registration_form_id, $auctionB->players()->first()->registration->registration_form_id);
    }

    /**
     * @return array{0: Auction, 1: Auction}
     */
    private function twoStandaloneAuctions(): array
    {
        $auctions = app(AuctionService::class);
        $forms = app(RegistrationFormService::class);
        $host = $auctions->ensureStandaloneHost();
        $template = $forms->getOrCreateForTournament($host);

        $auctionA = $auctions->createDraft($host, $template, 'Alpha Cup');
        $auctionB = $auctions->createDraft($host, $template, 'Beta Cup');

        return [$auctionA->fresh('registrationForm'), $auctionB->fresh('registrationForm')];
    }
}
