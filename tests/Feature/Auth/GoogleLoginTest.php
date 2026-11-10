<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\GoogleIdentityVerifier;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'test-client.apps.googleusercontent.com');

        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $this->privateKey);
        $details = openssl_pkey_get_details($key);

        Http::fake([
            GoogleIdentityVerifier::CERTS_URL => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'kid' => 'test-key',
                    'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
                    'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
                ]],
            ]),
        ]);
    }

    public function test_existing_account_can_sign_in_with_verified_google_identity(): void
    {
        $user = User::factory()->create(['email' => 'student@gmail.com', 'email_verified_at' => null]);
        $nonce = $this->challenge();

        $this->post(route('login.google'), [
            'credential' => $this->token($nonce, ['email' => 'student@gmail.com']),
            'nonce' => $nonce,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-user-123', $user->fresh()->google_sub);
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->post(route('logout'))->assertRedirect('/');
        $nonce = $this->challenge();

        $this->post(route('login.google'), [
            'credential' => $this->token($nonce, ['email' => 'student@gmail.com']),
            'nonce' => $nonce,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_google_login_requires_existing_account(): void
    {
        $nonce = $this->challenge();

        $this->post(route('login.google'), [
            'credential' => $this->token($nonce, ['email' => 'missing@gmail.com']),
            'nonce' => $nonce,
        ])->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_login_rejects_invalid_audience_and_replayed_challenge(): void
    {
        $user = User::factory()->create(['email' => 'student@gmail.com']);
        $nonce = $this->challenge();

        $this->post(route('login.google'), [
            'credential' => $this->token($nonce, ['aud' => 'another-client.apps.googleusercontent.com', 'email' => $user->email]),
            'nonce' => $nonce,
        ])->assertSessionHasErrors('google');

        $this->post(route('login.google'), [
            'credential' => $this->token($nonce, ['email' => $user->email]),
            'nonce' => $nonce,
        ])->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_sub);
    }

    public function test_google_login_rejects_unverified_or_non_authoritative_email(): void
    {
        $user = User::factory()->create(['email' => 'student@gmail.com']);
        $nonce = $this->challenge();

        $this->post(route('login.google'), [
            'credential' => $this->token($nonce, ['email' => $user->email, 'email_verified' => false]),
            'nonce' => $nonce,
        ])->assertSessionHasErrors('google');

        $external = User::factory()->create(['email' => 'external@example.com']);
        $nonce = $this->challenge();

        $this->post(route('login.google'), [
            'credential' => $this->token($nonce, ['email' => $external->email]),
            'nonce' => $nonce,
        ])->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($external->fresh()->google_sub);
    }

    public function test_linked_google_identity_cannot_access_account_after_email_change(): void
    {
        $user = User::factory()->create([
            'email' => 'old@gmail.com',
            'google_sub' => 'google-user-123',
        ]);
        $nonce = $this->challenge();

        $this->post(route('login.google'), [
            'credential' => $this->token($nonce, ['email' => 'new@gmail.com']),
            'nonce' => $nonce,
        ])->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame('old@gmail.com', $user->fresh()->email);
    }

    public function test_google_login_is_hidden_when_not_configured(): void
    {
        config()->set('services.google.client_id', null);

        $this->get(route('login.google.challenge'))
            ->assertOk()
            ->assertJson(['enabled' => false]);

        $this->post(route('login.google'), ['credential' => 'token', 'nonce' => str_repeat('a', 64)])
            ->assertNotFound();
    }

    private function challenge(): string
    {
        $response = $this->get(route('login.google.challenge'));
        $response->assertOk()->assertJson(['enabled' => true]);

        return $response->json('nonce');
    }

    /** @param  array<string, mixed>  $overrides */
    private function token(string $nonce, array $overrides = []): string
    {
        return JWT::encode([
            'iss' => 'https://accounts.google.com',
            'aud' => config('services.google.client_id'),
            'exp' => time() + 300,
            'iat' => time(),
            'sub' => 'google-user-123',
            'nonce' => $nonce,
            'email' => 'student@gmail.com',
            'email_verified' => true,
            ...$overrides,
        ], $this->privateKey, 'RS256', 'test-key');
    }
}
