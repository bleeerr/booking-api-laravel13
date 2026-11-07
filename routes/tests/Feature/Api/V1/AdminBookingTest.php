<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_all_bookings(): void
    {
        $this->getJson('/api/v1/admin/bookings')
            ->assertUnauthorized();
    }

    public function test_regular_user_cannot_list_all_bookings(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/bookings')
            ->assertForbidden();
    }

    public function test_admin_can_see_bookings_of_all_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $first = Booking::factory()->for($firstUser)->create();
        $second = Booking::factory()->for($secondUser)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/bookings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_can_paginate_all_bookings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Booking::factory()->count(5)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/bookings?page=2&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonMissingPath('links');
    }

    public function test_admin_receives_validation_error_for_invalid_pagination(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/bookings?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 422);
    }

    public function test_regular_booking_list_remains_scoped_to_current_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $otherUser = User::factory()->create();

        Booking::factory()->for($admin)->create();
        Booking::factory()->for($otherUser)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $admin->id)
            ->assertJsonPath('meta.total', 1);
    }
}
