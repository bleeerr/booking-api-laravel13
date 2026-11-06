<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Exceptions\Handlers\ApiExceptionRenderer;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_booking(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $room = Room::factory()->create([
            'price_per_hour_minor' => 1000,
            'currency_code' => 'PLN',
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2030-01-01T10:00:00+02:00',
            'ends_at' => '2030-01-01T11:30:00+02:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.room_id', $room->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_price_minor', 1500)
            ->assertJsonPath('data.currency_code', 'PLN')
            ->assertJsonPath('data.cancelled_at', null);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'room_id' => $room->id,
            'status' => 'pending',
            'total_price_minor' => 1500,
            'currency_code' => 'PLN',
            'cancelled_at' => null,
        ]);
    }

    public function test_guest_cannot_create_booking(): void
    {
        $this->postJson('/api/v1/bookings', [])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 401);
    }

    public function test_invalid_interval_returns_422(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2030-01-01T12:00:00Z',
            'ends_at' => '2030-01-01T10:00:00Z',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 422);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_cannot_book_inactive_room(): void
    {
        $user = User::factory()->create();

        $room = Room::factory()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2030-01-01T10:00:00Z',
            'ends_at' => '2030-01-01T11:00:00Z',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 2003)
            ->assertJsonPath('error.message', 'Room is inactive');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_cannot_book_occupied_room(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => BookingStatus::Confirmed,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2030-01-01T09:30:00Z',
            'ends_at' => '2030-01-01T10:30:00Z',
        ])
            ->assertConflict()
            ->assertJsonPath('error.code', 3001)
            ->assertJsonPath(
                'error.message',
                'Room is not available for the selected period'
            );

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_database_overlap_returns_room_not_available(): void
    {
        $room = Room::factory()->create();
        $user = User::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => BookingStatus::Confirmed,
        ]);

        try {
            // Обходим предварительную проверку в BookingController.
            DB::table('bookings')->insert([
                'user_id' => $user->id,
                'room_id' => $room->id,
                'status' => BookingStatus::Pending->value,
                'starts_at' => '2030-01-01 09:30:00+00',
                'ends_at' => '2030-01-01 10:30:00+00',
                'total_price_minor' => 1000,
                'currency_code' => 'PLN',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('Expected PostgreSQL exclusion violation.');
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->errorInfo[0]);

            $response = app(ApiExceptionRenderer::class)
                ->renderException(
                    $exception,
                    Request::create('/api/v1/bookings', 'POST')
                );

            $this->assertNotNull($response);
            $this->assertSame(409, $response->getStatusCode());
            $this->assertSame(
                3001,
                $response->getData(true)['error']['code']
            );
        }
    }

    public function test_cancelled_booking_does_not_block_new_booking(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2030-01-01T09:00:00Z',
            'ends_at' => '2030-01-01T10:00:00Z',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.room_id', $room->id);

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_adjacent_booking_intervals_are_allowed(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => BookingStatus::Confirmed,
        ]);

        Sanctum::actingAs($user);

        // Существующее бронирование: 09:00–10:00 UTC.
        // Новое бронирование: 10:00–11:00 UTC.
        $this->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'starts_at' => '2030-01-01T10:00:00Z',
            'ends_at' => '2030-01-01T11:00:00Z',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.room_id', $room->id);

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_user_can_only_see_own_bookings(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Booking::factory()->for($user)->create([
            'starts_at' => '2030-01-01T09:00:00Z',
            'ends_at' => '2030-01-01T10:00:00Z',
        ]);

        Booking::factory()->for($otherUser)->create([
            'starts_at' => '2030-01-01T11:00:00Z',
            'ends_at' => '2030-01-01T12:00:00Z',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $user->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_user_can_get_paginated_bookings(): void
    {
        $user = User::factory()->create();

        // Каждое бронирование получает отдельную комнату,
        // чтобы не нарушить PostgreSQL exclusion constraint.
        for ($i = 0; $i < 5; $i++) {
            Booking::factory()
                ->for($user)
                ->create();
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/bookings?page=2&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonMissingPath('links');
    }

    public function test_user_can_view_own_booking(): void
    {
        $user = User::factory()->create();

        $booking = Booking::factory()
            ->for($user)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_user_cannot_view_another_users_booking(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $booking = Booking::factory()
            ->for($otherUser)
            ->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertForbidden()
            ->assertJsonPath('error.code', 403)
            ->assertJsonPath('error.message', 'Forbidden');
    }

    public function test_admin_can_view_any_booking(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create();

        $booking = Booking::factory()
            ->for($user)
            ->create();

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_guest_cannot_view_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 401);
    }

    public function test_nonexistent_booking_returns_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/bookings/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 404)
            ->assertJsonPath('error.message', 'Not Found');
    }

    public function test_user_can_cancel_pending_booking(): void
    {
        $user = User::factory()->create();

        $booking = Booking::factory()
            ->for($user)
            ->create([
                'status' => BookingStatus::Pending,
            ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.user_id', $user->id);

        $booking->refresh();

        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_user_can_cancel_confirmed_booking(): void
    {
        $user = User::factory()->create();

        $booking = Booking::factory()
            ->for($user)
            ->create([
                'status' => BookingStatus::Confirmed,
            ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.user_id', $user->id);

        $booking->refresh();

        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_admin_can_cancel_another_users_booking(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create();

        $booking = Booking::factory()
            ->for($user)
            ->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.user_id', $user->id);

        $booking->refresh();

        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_user_cannot_cancel_another_users_booking(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $booking = Booking::factory()
            ->for($otherUser)
            ->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertForbidden()
            ->assertJsonPath('error.code', 403);

        $booking->refresh();

        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertNull($booking->cancelled_at);
    }

    public function test_cannot_cancel_already_cancelled_booking(): void
    {
        $user = User::factory()->create();

        $booking = Booking::factory()
            ->for($user)
            ->create([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now()->subHour(),
            ]);

        $originalCancelledAt = $booking->cancelled_at;

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertConflict()
            ->assertJsonPath('error.code', 3002)
            ->assertJsonPath(
                'error.message',
                'Booking is already cancelled'
            );

        $booking->refresh();

        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertTrue(
            $originalCancelledAt->equalTo($booking->cancelled_at)
        );
    }

    public function test_guest_cannot_cancel_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->postJson("/api/v1/bookings/{$booking->id}/cancel")
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 401);
    }

    public function test_cancel_nonexistent_booking_returns_404(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/bookings/999999/cancel')
            ->assertNotFound()
            ->assertJsonPath('error.code', 404);
    }

    public function test_admin_can_confirm_pending_booking(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.id', $booking->id)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.cancelled_at', null);

        $booking->refresh();

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->cancelled_at);
    }

    public function test_admin_cannot_confirm_already_confirmed_booking(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")
            ->assertConflict()
            ->assertJsonPath('error.code', 3003)
            ->assertJsonPath(
                'error.message',
                'Booking cannot be confirmed in its current status'
            );

        $this->assertSame(
            BookingStatus::Confirmed,
            $booking->fresh()->status
        );
    }

    public function test_admin_cannot_confirm_cancelled_booking(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now()->subHour(),
        ]);

        $originalCancelledAt = $booking->cancelled_at;

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")
            ->assertConflict()
            ->assertJsonPath('error.code', 3003);

        $booking->refresh();

        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertTrue(
            $originalCancelledAt->equalTo($booking->cancelled_at)
        );
    }

    public function test_user_cannot_confirm_booking(): void
    {
        $user = User::factory()->create();

        $booking = Booking::factory()
            ->for($user)
            ->create([
                'status' => BookingStatus::Pending,
            ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")
            ->assertForbidden()
            ->assertJsonPath('error.code', 403);

        $this->assertSame(
            BookingStatus::Pending,
            $booking->fresh()->status
        );
    }

    public function test_guest_cannot_confirm_booking(): void
    {
        $booking = Booking::factory()->create();

        $this->postJson("/api/v1/bookings/{$booking->id}/confirm")
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 401);
    }

    public function test_confirm_nonexistent_booking_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/bookings/999999/confirm')
            ->assertNotFound()
            ->assertJsonPath('error.code', 404);
    }
}
