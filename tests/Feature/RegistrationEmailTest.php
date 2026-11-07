<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_welcome_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'john@example.com');

        $user = User::query()
            ->where('email', 'john@example.com')
            ->firstOrFail();

        $this->assertTrue(
            Hash::check('SecurePassword123!', $user->password)
        );

        Mail::assertSent(
            WelcomeMail::class,
            fn (WelcomeMail $mail): bool => $mail->hasTo('john@example.com')
                && $mail->user->id === $user->id
        );

        Mail::assertSentCount(1);
    }
}
