<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\BankAccount;
use Illuminate\Database\Seeder;

/**
 * Akun tetap untuk uji Playwright. Hanya dijalankan pada database E2E, tidak ikut DatabaseSeeder.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        abort_if(app()->isProduction(), 403);

        $this->call([ModuleSeeder::class, RoleSeeder::class, FinanceSeeder::class]);

        User::create(['name' => 'Admin Uji', 'email' => 'admin@uji.test', 'type' => 'staff', 'password' => 'sandi-uji-admin'])->assignRole('admin');

        User::create(['name' => 'Bendahara Uji', 'email' => 'bendahara@uji.test', 'type' => 'staff', 'password' => 'sandi-uji-bendahara'])->assignRole('bendahara');
        BankAccount::create(['bank_name' => 'Bank Uji', 'account_no' => '7001234567', 'account_holder' => 'Pesantren Uji']);

        $guardianUser = User::create(['name' => 'Wali Uji', 'phone' => '6281234567890', 'type' => 'guardian']);
        $guardianUser->assignRole('wali');
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'name' => 'Wali Uji', 'phone_wa' => '6281234567890']);

        $studentUser = User::create(['name' => 'Santri Uji', 'username' => '2026001', 'type' => 'student', 'password' => 'sandi-uji-santri']);
        $studentUser->assignRole('santri');
        $student = Student::create(['nis' => '2026001', 'name' => 'Santri Uji', 'gender' => 'L', 'user_id' => $studentUser->id]);

        $guardian->students()->attach($student->id, ['relation' => 'ayah', 'is_primary' => true]);

        $payerUser = User::create(['name' => 'Ibu Uji', 'phone' => '6281234567891', 'type' => 'guardian']);
        $payerUser->assignRole('wali');
        Guardian::create(['user_id' => $payerUser->id, 'name' => 'Ibu Uji', 'phone_wa' => '6281234567891'])
            ->students()->attach($student->id, ['relation' => 'ibu']);
    }
}
