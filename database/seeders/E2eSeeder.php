<?php

namespace Database\Seeders;

use App\Modules\Core\Actions\SaveIntegration;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
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
        app(SaveIntegration::class)->handle('midtrans', 'sandbox', ['server_key' => 'kunci-server-e2e', 'client_key' => 'kunci-klien-e2e'], [], true);
        Module::query()->where('module_key', 'payment_midtrans')->update(['is_enabled' => true]);
        $cashier = User::create(['name' => 'Kasir Uji', 'email' => 'kasir@uji.test', 'type' => 'staff', 'password' => 'sandi-uji-kasir']);
        $cashier->assignRole('kasir');
        $manager = User::create(['name' => 'Pengelola Uji', 'email' => 'pengelola@uji.test', 'type' => 'staff', 'password' => 'sandi-uji-pengelola']);
        $manager->assignRole('pengelola_merchant');
        $merchant = Merchant::create(['name' => 'Warung Uji', 'type' => 'warung', 'pic_user_id' => $manager->id, 'payment_modes' => ['card', 'qr']]);
        $merchant->cashiers()->attach($cashier->id);
        MerchantProduct::create(['merchant_id' => $merchant->id, 'name' => 'Roti Uji', 'price' => 5000, 'barcode' => '8990001']);
        MerchantProduct::create(['merchant_id' => $merchant->id, 'name' => 'Paket Besar', 'price' => 25000]);
        BankAccount::create(['bank_name' => 'Bank Uji', 'account_no' => '7001234567', 'account_holder' => 'Pesantren Uji']);

        $guardianUser = User::create(['name' => 'Wali Uji', 'phone' => '6281234567890', 'type' => 'guardian']);
        $guardianUser->assignRole('wali');
        $guardian = Guardian::create(['user_id' => $guardianUser->id, 'name' => 'Wali Uji', 'phone_wa' => '6281234567890']);

        $studentUser = User::create(['name' => 'Santri Uji', 'username' => '2026001', 'type' => 'student', 'password' => 'sandi-uji-santri']);
        $studentUser->assignRole('santri');
        $student = Student::create(['nis' => '2026001', 'name' => 'Santri Uji', 'gender' => 'L', 'user_id' => $studentUser->id]);

        $guardian->students()->attach($student->id, ['relation' => 'ayah', 'is_primary' => true]);

        $secondGuardianUser = User::create(['name' => 'Wali Dua', 'phone' => '6281234567892', 'type' => 'guardian']);
        $secondGuardianUser->assignRole('wali');
        $second = Student::create(['nis' => '2026002', 'name' => 'Santri Dua', 'gender' => 'L', 'status' => 'inactive']);
        Guardian::create(['user_id' => $secondGuardianUser->id, 'name' => 'Wali Dua', 'phone_wa' => '6281234567892'])->students()->attach($second->id, ['relation' => 'ayah']);

        $teacherUser = User::create(['name' => 'Ustadz Uji', 'email' => 'ustadz@uji.test', 'type' => 'staff', 'password' => 'sandi-uji-ustadz']);
        $teacherUser->assignRole('ustadz');
        Teacher::create(['user_id' => $teacherUser->id, 'name' => 'Ustadz Uji']);
        $year = AcademicYear::create(['name' => '2026/2027 Uji', 'start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'is_active' => true]);
        Term::create(['academic_year_id' => $year->id, 'name' => 'Term Uji', 'start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'is_active' => true]);
        $classroom = Classroom::create(['name' => 'Kelas Uji 6']);
        $thirdUser = User::create(['name' => 'Santri Tiga', 'username' => '2026003', 'type' => 'student', 'password' => 'sandi-uji-tiga']);
        $thirdUser->assignRole('santri');
        // Tidak punya tagihan generate karena jenis tagihan E2E dibatasi di spec; terhubung ke wali tersendiri.
        $third = Student::create(['nis' => '2026003', 'name' => 'Santri Tiga', 'gender' => 'L', 'classroom_id' => $classroom->id, 'user_id' => $thirdUser->id]);
        $thirdGuardianUser = User::create(['name' => 'Wali Tiga', 'phone' => '6281234567893', 'type' => 'guardian']);
        $thirdGuardianUser->assignRole('wali');
        Guardian::create(['user_id' => $thirdGuardianUser->id, 'name' => 'Wali Tiga', 'phone_wa' => '6281234567893'])->students()->attach($third->id, ['relation' => 'ayah']);

        $payerUser = User::create(['name' => 'Ibu Uji', 'phone' => '6281234567891', 'type' => 'guardian']);
        $payerUser->assignRole('wali');
        Guardian::create(['user_id' => $payerUser->id, 'name' => 'Ibu Uji', 'phone_wa' => '6281234567891'])
            ->students()->attach($student->id, ['relation' => 'ibu']);
    }
}
