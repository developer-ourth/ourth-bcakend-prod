<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Register
    // ──────────────────────────────────────────────────────────

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'consumer',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'role']]]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'role' => 'consumer']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'consumer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_with_invalid_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Bad Role',
            'email' => 'bad@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'hacker',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_register_fails_when_password_not_confirmed(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password123',
            'role' => 'consumer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ──────────────────────────────────────────────────────────
    // Login
    // ──────────────────────────────────────────────────────────

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('secret1234'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'role']]]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => bcrypt('correct'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'incorrect',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_for_user_with_no_valid_role(): void
    {
        User::factory()->create([
            'email' => 'norole@example.com',
            'password' => bcrypt('password123'),
            'role' => 'guest', // not in ALLOWED_ROLES list
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'norole@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────
    // Logout
    // ──────────────────────────────────────────────────────────

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $token = $user->createToken('dashboard')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Token must be removed from the database after logout
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ──────────────────────────────────────────────────────────
    // Get Authenticated User
    // ──────────────────────────────────────────────────────────

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create(['role' => 'consumer']);
        $token = $user->createToken('dashboard')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/user')
            ->assertStatus(200)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'consumer');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/auth/user')->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────
    // Forgot Password
    // ──────────────────────────────────────────────────────────

    public function test_forgot_password_sends_reset_link_for_existing_email(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andReturn(Password::RESET_LINK_SENT);

        User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_forgot_password_fails_with_invalid_email_format(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_returns_422_for_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(422);
    }
}
