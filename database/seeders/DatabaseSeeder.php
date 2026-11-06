<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException(
                'Demo seeding is allowed only in local or testing environments.'
            );
        }

        $this->call([
            UserSeeder::class,
            WorkspaceSeeder::class,
            RoomSeeder::class,
            BookingSeeder::class,
        ]);
    }
}
