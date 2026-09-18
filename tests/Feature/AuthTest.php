<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_is_public(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_operator_can_sign_in_and_open_the_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'op@example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'op@example.com',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'op@example.com']);

        $this->from('/login')->post('/login', [
            'email' => 'op@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_first_operator_can_register_then_later_signups_are_closed(): void
    {
        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'First Op',
            'email' => 'first@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->post('/logout');

        $this->get('/register')->assertRedirect('/login');
        $this->post('/register', [
            'name' => 'Second',
            'email' => 'second@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertRedirect('/login');
    }

    public function test_auction_commands_require_authentication(): void
    {
        $this->postJson('/api/auctions/1/bid', [
            'team_id' => 1,
            'amount' => 1000,
        ])->assertUnauthorized();
    }

    public function test_signed_in_operator_can_create_another_operator(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/account/operators', [
            'name' => 'Desk Two',
            'email' => 'desk2@example.com',
            'password' => 'password1',
            'password_confirmation' => 'password1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'desk2@example.com']);
    }
}
