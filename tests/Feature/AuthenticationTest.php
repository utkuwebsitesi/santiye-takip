<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_caching_is_disabled_for_web_responses(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0')
            ->assertSee('assets/app.css?v=', false);
    }

    public function test_login_success_failure_inactive_and_rate_limit(): void
    {
        $active = User::factory()->create(['username' => 'aktif', 'password' => Hash::make('Strong-Pass-2026!')]);
        User::factory()->create(['username' => 'pasif', 'password' => Hash::make('Strong-Pass-2026!'), 'is_active' => false]);

        $this->post('/giris', $this->credentials('aktif', 'yanlis'))->assertSessionHasErrors('username');
        $this->post('/giris', $this->credentials('pasif', 'Strong-Pass-2026!'))->assertSessionHasErrors('username');
        $this->post('/giris', $this->credentials('aktif', 'Strong-Pass-2026!'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($active);

        auth()->logout();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/giris', $this->credentials('limit', 'yanlis'));
        }
        $this->post('/giris', $this->credentials('limit', 'yanlis'))->assertStatus(429);
    }

    public function test_captcha_is_required_correct_and_single_use(): void
    {
        User::factory()->create(['username' => 'captcha', 'password' => Hash::make('Strong-Pass-2026!')]);

        $payload = $this->credentials('captcha', 'Strong-Pass-2026!');
        $payload['captcha'] = '999';
        $this->post(route('login'), $payload)
            ->assertSessionHasErrors('captcha');
        $this->assertGuest();

        $payload = $this->credentials('captcha', 'Strong-Pass-2026!');
        $this->post(route('login'), $payload)->assertRedirect(route('dashboard'));
        auth()->logout();
        $this->post(route('login'), $payload)->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_captcha_remains_valid_when_login_page_is_opened_again(): void
    {
        $user = User::factory()->create([
            'username' => 'sekmeli',
            'password' => Hash::make('Strong-Pass-2026!'),
        ]);

        $firstPage = $this->get(route('login'));
        $this->get(route('login'));
        [$token, $answer] = $this->captchaFrom($firstPage->getContent());

        $this->post(route('login'), [
            'username' => 'sekmeli',
            'password' => 'Strong-Pass-2026!',
            'captcha' => $answer,
            'captcha_token' => $token,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_existing_session_is_terminated(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_authenticated_session_expires_after_fifteen_minutes_of_inactivity(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->travel(14)->minutes();
        $this->get(route('dashboard'))->assertOk();
        $this->travel(16)->minutes();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_does_not_offer_persistent_session(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Bu cihazda beni hatırla');

        $this->assertTrue(config('session.expire_on_close'));
        $this->assertSame(15, config('session.idle_timeout'));
    }

    private function credentials(string $username, string $password): array
    {
        $response = $this->get(route('login'));
        [$token, $answer] = $this->captchaFrom($response->getContent());

        return [
            'username' => $username,
            'password' => $password,
            'captcha' => $answer,
            'captcha_token' => $token,
        ];
    }

    /**
     * @return array{string, string}
     */
    private function captchaFrom(string $html): array
    {
        preg_match('/name="captcha_token" value="([^"]+)"/', $html, $matches);
        $token = html_entity_decode($matches[1] ?? '', ENT_QUOTES);
        $challenge = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);

        return [$token, $challenge['answer']];
    }
}
