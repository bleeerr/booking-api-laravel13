<?php

namespace Tests\Feature\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_get_empty_room_list(): void
    {
        $workspace = Workspace::factory()->create();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/rooms")
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => [
                    'page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
    }

    public function test_guest_can_get_active_rooms_only(): void
    {
        $workspace = Workspace::factory()->create();

        Room::factory()->for($workspace)->create([
            'name' => 'Active Room',
            'is_active' => true,
        ]);

        Room::factory()->for($workspace)->create([
            'name' => 'Inactive Room',
            'is_active' => false,
        ]);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/rooms")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Room')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_rooms_belong_only_to_requested_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $otherWorkspace = Workspace::factory()->create();

        Room::factory()->for($workspace)->create([
            'name' => 'Requested Room',
        ]);

        Room::factory()->for($otherWorkspace)->create([
            'name' => 'Other Room',
        ]);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/rooms")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Requested Room');
    }

    public function test_guest_can_get_paginated_room_list(): void
    {
        $workspace = Workspace::factory()->create();

        Room::factory()->for($workspace)->count(5)->create();

        $this->getJson(
            "/api/v1/workspaces/{$workspace->id}/rooms?page=2&per_page=2"
        )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonMissingPath('links');
    }

    public function test_guest_gets_404_for_nonexistent_workspace(): void
    {
        $this->getJson('/api/v1/workspaces/999999/rooms')
            ->assertNotFound()
            ->assertJsonPath('error.code', 404);
    }

    public function test_invalid_pagination_returns_422(): void
    {
        $workspace = Workspace::factory()->create();

        $this->getJson(
            "/api/v1/workspaces/{$workspace->id}/rooms?per_page=101"
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 422);
    }

    public function test_room_response_matches_contract(): void
    {
        $workspace = Workspace::factory()->create();

        $room = Room::factory()->for($workspace)->create();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/rooms")
            ->assertOk()
            ->assertJsonPath('data.0.id', $room->id)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'workspace_id',
                        'name',
                        'description',
                        'capacity',
                        'price_per_hour_minor',
                        'currency_code',
                        'is_active',
                    ],
                ],
                'meta' => [
                    'page',
                    'per_page',
                    'total',
                    'last_page',
                ],
            ]);
    }

    public function test_admin_can_create_room(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'Meeting Room',
            'description' => 'Room for meetings',
            'capacity' => 8,
            'price_per_hour_minor' => 5000,
            'currency_code' => 'PLN',
            'is_active' => true,
        ];

        $this->postJson(
            "/api/v1/workspaces/{$workspace->id}/rooms",
            $data
        )
            ->assertCreated()
            ->assertJsonPath('data.name', 'Meeting Room')
            ->assertJsonPath('data.workspace_id', $workspace->id)
            ->assertJsonPath('data.capacity', 8);

        $this->assertDatabaseHas('rooms', [
            ...$data,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_user_cannot_create_room(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(
            "/api/v1/workspaces/{$workspace->id}/rooms",
            [
                'name' => 'Unauthorized Room',
                'capacity' => 5,
                'price_per_hour_minor' => 1000,
                'currency_code' => 'PLN',
                'is_active' => true,
            ]
        )->assertForbidden()
            ->assertJsonPath('error.code', 403);

        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_guest_cannot_create_room(): void
    {
        $workspace = Workspace::factory()->create();

        $this->postJson(
            "/api/v1/workspaces/{$workspace->id}/rooms",
            []
        )->assertUnauthorized()
            ->assertJsonPath('error.code', 401);
    }

    public function test_admin_cannot_create_room_with_invalid_data(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/v1/workspaces/{$workspace->id}/rooms",
            [
                'name' => '',
                'capacity' => 0,
                'price_per_hour_minor' => -100,
                'currency_code' => 'INVALID',
                'is_active' => 'invalid',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 422)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                ],
            ]);

        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_admin_can_update_room(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $room = Room::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/rooms/{$room->id}", [
            'name' => 'Updated Room',
            'capacity' => 12,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Room')
            ->assertJsonPath('data.capacity', 12)
            ->assertJsonPath(
                'data.price_per_hour_minor',
                $room->price_per_hour_minor
            );

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Updated Room',
            'capacity' => 12,
        ]);
    }

    public function test_admin_can_clear_room_description(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $room = Room::factory()->create([
            'description' => 'Original description',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/rooms/{$room->id}", [
            'description' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.description', null);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'description' => null,
        ]);
    }

    public function test_user_cannot_update_room(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $room = Room::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/rooms/{$room->id}", [
            'name' => 'Unauthorized Update',
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 403);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => $room->name,
        ]);
    }

    public function test_guest_cannot_update_room(): void
    {
        $room = Room::factory()->create();

        $this->patchJson("/api/v1/rooms/{$room->id}", [
            'name' => 'Unauthorized Update',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 401);
    }

    public function test_admin_cannot_update_room_with_invalid_data(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $room = Room::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/rooms/{$room->id}", [
            'capacity' => 0,
            'price_per_hour_minor' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 422);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'capacity' => $room->capacity,
            'price_per_hour_minor' => $room->price_per_hour_minor,
        ]);
    }

    public function test_admin_can_delete_room_without_bookings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $room = Room::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/rooms/{$room->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('rooms', [
            'id' => $room->id,
        ]);
    }

    public function test_user_cannot_delete_room(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $room = Room::factory()->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/rooms/{$room->id}")
            ->assertForbidden()
            ->assertJsonPath('error.code', 403);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
        ]);
    }

    public function test_guest_cannot_delete_room(): void
    {
        $room = Room::factory()->create();

        $this->deleteJson("/api/v1/rooms/{$room->id}")
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 401);
    }

    public function test_update_nonexistent_room_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/rooms/999999', [
            'name' => 'Updated Room',
        ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 404);
    }

    public function test_delete_nonexistent_room_returns_404(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/rooms/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 404);
    }

    public function test_room_without_bookings_is_available(): void
    {
        $room = Room::factory()->create();

        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T10:00:00Z'
            .'&ends_at=2030-01-01T11:00:00Z'
        )
            ->assertOk()
            ->assertJsonPath('data.room_id', $room->id)
            ->assertJsonPath('data.available', true);
    }

    public function test_pending_booking_blocks_room(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => BookingStatus::Pending,
        ]);

        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T09:30:00Z'
            .'&ends_at=2030-01-01T10:30:00Z'
        )
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_confirmed_booking_blocks_room(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => BookingStatus::Confirmed,
        ]);

        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T09:00:00Z'
            .'&ends_at=2030-01-01T10:00:00Z'
        )
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_cancelled_booking_does_not_block_room(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T09:00:00Z'
            .'&ends_at=2030-01-01T10:00:00Z'
        )
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }

    public function test_adjacent_intervals_do_not_overlap(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create();

        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T10:00:00Z'
            .'&ends_at=2030-01-01T11:00:00Z'
        )
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }

    public function test_inactive_room_is_unavailable(): void
    {
        $room = Room::factory()->create([
            'is_active' => false,
        ]);

        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T10:00:00Z'
            .'&ends_at=2030-01-01T11:00:00Z'
        )
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_availability_respects_timezone_offsets(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->for($room)->create();

        // 10:30 +02:00 = 08:30 UTC
        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T10:30:00%2B02:00'
            .'&ends_at=2030-01-01T11:30:00%2B02:00'
        )
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_invalid_availability_interval_returns_422(): void
    {
        $room = Room::factory()->create();

        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T11:00:00Z'
            .'&ends_at=2030-01-01T10:00:00Z'
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 422);
    }

    public function test_availability_requires_timezone_offset(): void
    {
        $room = Room::factory()->create();

        $this->getJson(
            "/api/v1/rooms/{$room->id}/availability"
            .'?starts_at=2030-01-01T10:00:00'
            .'&ends_at=2030-01-01T11:00:00'
        )
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 422);
    }

    public function test_availability_for_unknown_room_returns_404(): void
    {
        $this->getJson(
            '/api/v1/rooms/999999/availability'
            .'?starts_at=2030-01-01T10:00:00Z'
            .'&ends_at=2030-01-01T11:00:00Z'
        )
            ->assertNotFound()
            ->assertJsonPath('error.code', 404);
    }

    public function test_admin_cannot_delete_room_with_bookings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $room = Room::factory()->create();

        Booking::factory()->for($room)->create([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/rooms/{$room->id}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 2002);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
        ]);
    }
}
