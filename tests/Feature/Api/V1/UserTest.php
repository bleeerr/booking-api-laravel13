<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register(): void
    {
        $data = $this->registrationData();

        $response = $this->postJson('/api/v1/register', $data);

        $response->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => $response->json('data.id'),
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => UserRole::User->value,
                ],
            ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertNotSame($data['password'], $user->getRawOriginal('password'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_register_does_not_allow_assigning_admin_role(): void
    {
        $data = $this->registrationData();
        $data['role'] = UserRole::Admin->value;

        $this->postJson('/api/v1/register', $data)
            ->assertCreated()
            ->assertJsonPath('data.role', UserRole::User->value);

        $this->assertDatabaseHas('users', [
            'email' => $data['email'],
            'role' => UserRole::User->value,
        ]);
    }

    public function test_register_rejects_missing_required_fields(): void
    {
        $this->postJson('/api/v1/register', [])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'name',
                        'email',
                        'password',
                        'password_confirmation',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_invalid_email_and_short_password(): void
    {
        $this->postJson('/api/v1/register', $this->registrationData([
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => ['details' => ['email', 'password']],
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/v1/register', $this->registrationData([
            'password_confirmation' => 'DifferentPassword123!',
        ]))
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => ['details' => ['password']],
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseCount('users', 1);

        $this->postJson('/api/v1/register', [
            'name' => 'Another User',
            'email' => $user->email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertConflict()
            ->assertJsonPath(
                'error.code',
                ApiErrorCode::EmailAlreadyExists->value
            );
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('Password123!'),
            'role' => UserRole::User,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'token_type']]);

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->id,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_rejects_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword123!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', ApiErrorCode::InvalidCredentials->value);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'missing@example.com',
            'password' => 'Password123!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', ApiErrorCode::InvalidCredentials->value);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_validates_required_credentials(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => ['details' => ['email', 'password']],
            ]);
    }

    public function test_guest_cannot_access_me(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_me(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => UserRole::Admin->value,
                ],
            ]);
    }

    public function test_invalid_token_cannot_access_me(): void
    {
        $this->withToken('invalid-token')
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_logout(): void
    {
        $this->postJson('/api/v1/logout')->assertUnauthorized();
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();

        $currentToken = $user->createToken('current-device')->plainTextToken;
        $otherToken = $user->createToken('other-device')->plainTextToken;

        $this->withToken($currentToken)
            ->postJson('/api/v1/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        Auth::forgetGuards();

        $this->withToken($currentToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        Auth::forgetGuards();

        $this->withToken($otherToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_register_is_rate_limited(): void
    {
        $attempts = (int) config('api.rate_limits.register.max_attempts');

        for ($i = 0; $i < $attempts; $i++) {
            $this->postJson('/api/v1/register', [])->assertUnprocessable();
        }

        $this->postJson('/api/v1/register', [])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_login_is_rate_limited(): void
    {
        $attempts = (int) config('api.rate_limits.login.max_attempts');
        $credentials = [
            'email' => 'missing@example.com',
            'password' => 'WrongPassword123!',
        ];

        for ($i = 0; $i < $attempts; $i++) {
            $this->postJson('/api/v1/login', $credentials)
                ->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', $credentials)
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registrationData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $overrides);
    }
}
