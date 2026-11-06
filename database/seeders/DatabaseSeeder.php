<?php

namespace Database\Seeders;

use App\Modules\Core\Actions\CreateFirstAdmin;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ModuleSeeder::class, RoleSeeder::class]);

        // Admin pertama dibuat lewat seeder (PRD UI/UX 5.6); sandinya hanya tampil sekali.
        if ($email = env('SANAD_ADMIN_EMAIL')) {
            $password = app(CreateFirstAdmin::class)->handle($email);

            if ($password !== null) {
                $this->command?->warn("Admin {$email} dibuat. Sandi awal: {$password}");
            }
        }
    }
}
