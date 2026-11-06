<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_isolated_test_database(): void
    {
        $this->assertSame('testing', app()->environment());

        $this->assertSame('pgsql', config('database.default'));

        $this->assertSame(
            'booking_api_test',
            config('database.connections.pgsql.database')
        );

        $database = DB::selectOne(
            'SELECT current_database() AS name'
        );

        $this->assertSame('booking_api_test', $database->name);
    }

    public function test_guest_can_get_empty_workspace_list(): void
    {
        $response = $this->getJson('/api/v1/workspaces');

        $response->assertOk()
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

    public function test_guest_can_get_paginated_workspace_list(): void
    {
        Workspace::factory()->demo()->count(5)->create();

        $response = $this->getJson(
            '/api/v1/workspaces?page=2&per_page=2'
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonMissingPath('links');
    }

    public function test_guest_can_get_workspace_by_id(): void
    {
        $workspace = Workspace::factory()->demo()->create();

        $this->getJson("/api/v1/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'timezone' => $workspace->timezone,
                    'address' => $workspace->address,
                ],
            ]);
    }

    public function test_guest_gets_404_for_nonexistent_workspace(): void
    {
        $this->getJson('/api/v1/workspaces/999999')
            ->assertNotFound();
    }

    public function test_admin_can_create_workspace(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'name' => 'Test Coworking',
            'timezone' => 'Europe/Warsaw',
            'address' => 'Warsaw, Poland',
        ];

        $this->postJson('/api/v1/workspaces', $data)
            ->assertCreated()
            ->assertJsonPath('data.name', $data['name'])
            ->assertJsonPath('data.timezone', $data['timezone'])
            ->assertJsonPath('data.address', $data['address']);

        $this->assertDatabaseHas('workspaces', $data);
    }

    public function test_user_cannot_create_workspace(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        Sanctum::actingAs($user);

        $data = [
            'name' => 'Unauthorized Workspace',
            'timezone' => 'Europe/Warsaw',
            'address' => 'Warsaw, Poland',
        ];

        $this->postJson('/api/v1/workspaces', $data)
            ->assertForbidden();

        $this->assertDatabaseMissing('workspaces', $data);
    }

    public function test_guest_cannot_create_workspace(): void
    {
        $data = [
            'name' => 'Guest Workspace',
            'timezone' => 'Europe/Warsaw',
            'address' => 'Warsaw, Poland',
        ];

        $this->postJson('/api/v1/workspaces', $data)
            ->assertUnauthorized();

        $this->assertDatabaseMissing('workspaces', $data);
    }

    public function test_admin_cannot_create_workspace_with_invalid_data(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Sanctum::actingAs($admin);

        $data = [
            'name' => '',
            'timezone' => 'Invalid/Timezone',
            'address' => '',
        ];

        $this->postJson('/api/v1/workspaces', $data)
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                ],
            ]);

        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_admin_can_update_workspace(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workspace = Workspace::factory()->demo()->create();

        Sanctum::actingAs($admin);

        $data = ['name' => 'Updated Workspace'];

        $this->patchJson("/api/v1/workspaces/{$workspace->id}", $data)
            ->assertOk()
            ->assertJsonPath('data.name', $data['name'])
            ->assertJsonPath('data.timezone', $workspace->timezone)
            ->assertJsonPath('data.address', $workspace->address);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => $data['name'],
        ]);
    }

    public function test_user_cannot_update_workspace(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}", [
            'name' => 'Unauthorized Update',
        ])->assertForbidden();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => $workspace->name,
        ]);
    }

    public function test_admin_cannot_update_workspace_with_invalid_data(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}", [
            'timezone' => 'Invalid/Timezone',
        ])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                ],
            ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'timezone' => $workspace->timezone,
        ]);
    }

    public function test_admin_can_delete_workspace(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workspaces', [
            'id' => $workspace->id,
        ]);
    }

    public function test_user_cannot_delete_workspace(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $workspace = Workspace::factory()->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    }

    public function test_admin_cannot_delete_workspace_with_rooms(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $workspace = Workspace::factory()->create();

        Room::factory()->create([
            'workspace_id' => $workspace->id,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")
            ->assertConflict()
            ->assertJsonPath('error.code', ApiErrorCode::ResourceHasDependencies);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    }
}
