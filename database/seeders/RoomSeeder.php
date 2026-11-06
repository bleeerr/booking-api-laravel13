<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        Workspace::query()
            ->get()
            ->each(function (Workspace $workspace): void {
                Room::factory()
                    ->count(5)
                    ->demo()
                    ->for($workspace)
                    ->create();
            });
    }
}
