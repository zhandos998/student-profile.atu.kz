<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_error_is_translated_in_each_supported_locale(): void
    {
        foreach (['ru', 'kk', 'en'] as $locale) {
            $messages = require base_path("lang/{$locale}/passwords.php");

            $this->withSession(['locale' => $locale])
                ->post('/forgot-password', ['email' => 'missing@example.com'])
                ->assertSessionHasErrors(['email' => $messages['user']]);
        }
    }

    public function test_reset_link_success_is_translated_in_each_supported_locale(): void
    {
        Notification::fake();

        foreach (['ru', 'kk', 'en'] as $locale) {
            $messages = require base_path("lang/{$locale}/passwords.php");
            $user = User::factory()->create();

            $this->withSession(['locale' => $locale])
                ->post('/forgot-password', ['email' => $user->email])
                ->assertSessionHas('status', $messages['sent']);
        }
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
