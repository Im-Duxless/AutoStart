<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_assigns_student_and_logout_revokes_the_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/me')->assertUnauthorized();
        Auth::forgetGuards();

        $registration = $this->postJson('/api/register', [
            'name' => 'Анна Иванова', 'email' => 'ANNA@example.test', 'phone' => '+7 777 123 45 67',
            'password' => 'Drive12345', 'password_confirmation' => 'Drive12345', 'role' => 'admin',
        ])->assertCreated()->assertJsonPath('data.role', 'student')->assertJsonMissingPath('data.password');

        $user = User::where('email', 'anna@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Drive12345', $user->password));
        $this->withToken($registration->json('token'))->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->patchJson('/api/me', ['name' => 'Анна Петрова', 'role' => 'admin'])
            ->assertOk()->assertJsonPath('data.name', 'Анна Петрова')->assertJsonPath('data.role', 'student');
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/logout')->assertNoContent();

        Auth::forgetGuards();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_auth_validates_credentials_and_the_registered_email(): void
    {
        $user = User::factory()->instructor()->create();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'bad'])
            ->assertUnauthorized()->assertJsonPath('message', 'Неверный email или пароль.');
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'AutoStart!2026'])
            ->assertOk()->assertJsonPath('data.role', 'instructor')->assertJsonStructure(['token']);

        $this->postJson('/api/register', [
            'name' => 'Пользователь', 'email' => $user->email, 'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_cors_allows_the_configured_origin(): void
    {
        $origin = config('cors.allowed_origins')[0];

        $this->call('OPTIONS', '/api/me', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', $origin);

        $otherOrigin = 'https://other.example';
        $response = $this->call('OPTIONS', '/api/me', server: [
            'HTTP_ORIGIN' => $otherOrigin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
        $this->assertNotSame($otherOrigin, $response->headers->get('Access-Control-Allow-Origin'));
    }
}
