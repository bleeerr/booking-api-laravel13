<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startsAt = CarbonImmutable::parse('2030-01-01 09:00:00', 'UTC');

        return [
            'user_id' => User::factory(),
            'room_id' => Room::factory(),
            'status' => 'pending',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'total_price_minor' => 1000,
            'currency_code' => 'PLN',
            'cancelled_at' => null,
        ];
    }

    public function demo(
        Room $room,
        CarbonImmutable $startsAt,
        int $hours,
        string $status,
    ): static {
        return $this->state(fn (array $attributes): array => [
            'room_id' => $room->id,
            'status' => $status,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours($hours),
            'total_price_minor' => $room->price_per_hour_minor * $hours,
            'currency_code' => $room->currency_code,
            'cancelled_at' => $status === 'cancelled'
                ? CarbonImmutable::now('UTC')
                : null,
        ]);
    }
}
