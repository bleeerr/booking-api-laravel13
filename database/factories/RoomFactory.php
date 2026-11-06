<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => 'Test Room',
            'description' => null,
            'capacity' => 1,
            'price_per_hour_minor' => 1000,
            'currency_code' => 'PLN',
            'is_active' => true,
        ];
    }

    public function demo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => fake()->randomElement([
                'Meeting Room',
                'Conference Hall',
                'Private Office',
                'Open Space',
                'Creative Studio',
            ]),
            'description' => fake()->sentence(),
            'capacity' => fake()->numberBetween(2, 30),
            'price_per_hour_minor' => fake()->numberBetween(2000, 15000),
            'currency_code' => 'PLN',
            'is_active' => fake()->boolean(90),
        ]);
    }
}
