<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Mail\BookingStatusMail;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_creation_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $room = Room::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2030-01-01T09:00:00Z',
            'ends_at' => '2030-01-01T10:00:00Z',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $bookingId = $response->json('data.id');

        Mail::assertSent(
            BookingStatusMail::class,
            fn (BookingStatusMail $mail): bool => $mail->hasTo($user->email)
                && $mail->subjectLine === 'Booking created'
                && $mail->booking->id === $bookingId
        );

        Mail::assertSentCount(1);
    }

    public function test_booking_confirmation_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Pending,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/v1/bookings/{$booking->id}/confirm"
        )->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        Mail::assertSent(
            BookingStatusMail::class,
            fn (BookingStatusMail $mail): bool => $mail->hasTo($user->email)
                && $mail->subjectLine === 'Booking confirmed'
        );

        Mail::assertSentCount(1);
    }

    public function test_booking_cancellation_sends_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::Confirmed,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/v1/bookings/{$booking->id}/cancel"
        )->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        Mail::assertSent(
            BookingStatusMail::class,
            fn (BookingStatusMail $mail): bool => $mail->hasTo($user->email)
                && $mail->subjectLine === 'Booking cancelled'
        );

        Mail::assertSentCount(1);
    }

    public function test_repeated_confirmation_does_not_send_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/v1/bookings/{$booking->id}/confirm"
        )->assertStatus(409);

        Mail::assertNothingSent();
    }
}
