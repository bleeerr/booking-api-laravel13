<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->where('role', 'user')
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            throw new RuntimeException('No users available for booking seeding.');
        }

        $rooms = Room::query()->orderBy('id')->get();

        $baseDate = CarbonImmutable::now('UTC')
            ->startOfDay()
            ->addDays(7);

        foreach ($rooms as $index => $room) {
            $user = $users[$index % $users->count()];

            $this->createBooking(
                $room,
                $user,
                $baseDate->addHours(9),
                1,
                'pending',
            );

            $this->createBooking(
                $room,
                $user,
                $baseDate->addHours(11),
                2,
                'confirmed',
            );

            $this->createBooking(
                $room,
                $user,
                $baseDate->addHours(9),
                1,
                'cancelled',
            );
        }
    }

    private function createBooking(
        Room $room,
        User $user,
        CarbonImmutable $startsAt,
        int $hours,
        string $status,
    ): void {
        Booking::factory()
            ->for($room)
            ->for($user)
            ->demo($room, $startsAt, $hours, $status)
            ->create();
    }
}
