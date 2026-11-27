<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_normalizes_email_hashes_password_and_authenticates(): void
    {
        $this->post('/register', [
            'name' => 'Alice', 'email' => 'ALICE@example.test',
            'password' => 'a-long-test-password', 'password_confirmation' => 'a-long-test-password',
        ])->assertRedirect(route('chat'));

        $user = User::where('email', 'alice@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
        $this->get(route('chat'))->assertOk()->assertSee('A little space');
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.test', 'password' => 'a-long-test-password']);
        $this->post('/login', ['email' => 'ALICE@example.test', 'password' => 'a-long-test-password'])
            ->assertRedirect(route('chat'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('chat'))->assertRedirect(route('login'));
    }

    public function test_login_rejects_wrong_credentials_and_throttles_repeated_failures(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please try again in a minute.']);
        $this->assertGuest();
    }

    public function test_registration_rejects_duplicate_email_and_weak_or_unconfirmed_passwords(): void
    {
        $user = User::factory()->create();
        $this->post('/register', ['name' => 'Someone', 'email' => $user->email, 'password' => 'short'])
            ->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_auth_pages_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Pick up where you left off.');
        $this->get(route('register'))->assertOk()->assertSee('Create an account');
    }
}
