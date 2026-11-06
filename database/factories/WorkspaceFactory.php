<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Test Workspace',
            'timezone' => 'UTC',
            'address' => 'Test Address',
        ];
    }

    public function demo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => fake()->company().' Coworking',
            'timezone' => 'Europe/Warsaw',
            'address' => fake()->address(),
        ]);
    }
}
