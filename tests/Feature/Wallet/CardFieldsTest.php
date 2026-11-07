<?php

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Wallet\Livewire\CardAdmin;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Support\CardFields;
use App\Support\SchoolIdentity;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::query()->whereIn('module_key', ['wallet', 'card'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();

    $this->student = Student::create(['nis' => 'S1', 'name' => 'Ahmad', 'gender' => 'L', 'entry_date' => '2026-07-13', 'classroom_id' => Classroom::create(['name' => 'Kelas Mawar'])->id, 'dormitory_id' => Dormitory::create(['name' => 'Abu Bakar', 'gender' => 'L'])->id]);
    $this->card = StudentCard::create(['student_id' => $this->student->id, 'qr_secret' => 'rahasia', 'status' => 'active', 'issued_at' => now()]);
});

function cardHtml($context): string
{
    return view('wallet.pdf.cards', ['cards' => collect([$context->card]), 'identity' => app(SchoolIdentity::class), 'profile' => SchoolProfile::current(), 'logoData' => null, 'yearName' => '2026/2027', 'format' => 'sheet', 'fields' => CardFields::selected()])->render();
}

it('bawaan: tidak memuat data yang gampang berubah', function () {
    expect(CardFields::selected())->toBe(['entry_year']);

    expect(cardHtml($this))->toContain('Ahmad')->toContain('Masuk')->toContain('2026')->not->toContain('Kelas Mawar')->not->toContain('Abu Bakar')->not->toContain('2026/2027');
});

it('admin memilih isi kartu; pilihan yang tidak dikenal dibuang; kasir tidak bisa', function () {
    $admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(CardAdmin::class)->assertSet('cardFields', ['entry_year'])
        ->set('cardFields', ['classroom', 'dormitory', 'academic_year', 'aneh'])->call('saveCardFields');

    expect(CardFields::selected())->toBe(['classroom', 'dormitory', 'academic_year']);
    expect(cardHtml($this))->toContain('Kelas Mawar')->toContain('Abu Bakar')->toContain('2026/2027')->not->toContain('Masuk');

    $this->actingAs(tap(User::create(['name' => 'Bendahara', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('kasir'));
    Livewire::test(CardAdmin::class)->call('saveCardFields')->assertForbidden();
});
