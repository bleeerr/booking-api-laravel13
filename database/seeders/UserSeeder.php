<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\OutputInterface;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->createDemoUser('Demo Admin', 'admin@example.com', UserRole::Admin);
        $this->createDemoUser('Demo User', 'user@example.com', UserRole::User);

        User::factory()
            ->count(9)
            ->demo()
            ->create();
    }

    private function createDemoUser(
        string $name,
        string $email,
        UserRole $role,
    ): void {
        $password = Str::password(16);

        User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        // Raw output prevents generated characters such as < and > from being
        // interpreted as Symfony Console markup (e.g. stray </info>).
        $this->command?->getOutput()->writeln(
            sprintf('%s | %s | %s', $role->value, $email, $password),
            OutputInterface::OUTPUT_RAW,
        );
    }
}
