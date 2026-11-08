<?php

use App\Modules\Core\Livewire\Settings\AuditLogs;
use App\Modules\Core\Livewire\Settings\IntegrationSettings;
use App\Modules\Core\Livewire\Settings\ModuleSettings;
use App\Modules\Core\Livewire\Settings\ProfileSettings;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Integration;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Support\Navigation;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function staff(string $role = 'admin'): User
{
    return tap(User::create(['name' => ucfirst($role), 'email' => $role.'@uji.test', 'type' => 'staff']))->assignRole($role);
}

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    $this->admin = staff();
    $this->actingAs($this->admin);
});

it('membuka semua layar pengaturan untuk admin', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['staff.settings.profile', 'staff.settings.modules', 'staff.settings.integrations', 'staff.settings.audit']);

it('menolak layar pengaturan untuk selain admin', function (string $role, string $route) {
    $this->actingAs(staff($role))->get(route($route))->assertForbidden();
})->with([
    ['bendahara', 'staff.settings.profile'],
    ['admin_akademik', 'staff.settings.modules'],
    ['ustadz', 'staff.settings.integrations'],
    ['kasir', 'staff.settings.audit'],
]);

it('menyembunyikan menu pengaturan dari selain admin', function () {
    $this->actingAs(staff('bendahara'));
    $keys = collect(app(Navigation::class)->staff())->flatMap(fn ($group) => $group['items'])->pluck('key');

    expect($keys)->not->toContain('profile')->not->toContain('audit');
});

it('sidebar ruang kerja: menu umum plus pintu masuk, di dalam ruang kerja hanya menunya sendiri', function () {
    Module::query()->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    $navigation = app(Navigation::class);
    $groups = $navigation->staff();

    $main = $navigation->sidebar($groups, 'students');
    $doors = collect($main['groups'])->firstWhere('label', 'Ruang kerja')['items'];
    expect($main['workspace'])->toBeNull()
        ->and(array_column($doors, 'label'))->toBe(['Keuangan', 'Mart & Kasir', 'PPDB', 'Masjid & Layar TV'])
        ->and($doors[0]['url'])->toBe(route('staff.invoices.index'))
        ->and(collect($main['groups'])->flatMap(fn ($group) => $group['items'])->pluck('key'))->not->toContain('invoices')->toContain('students');

    $mart = $navigation->sidebar($groups, 'merchants');
    expect($mart['workspace']['label'])->toBe('Mart & Kasir')
        ->and(collect($mart['groups'][0]['items'])->pluck('key')->all())->toBe(['pos', 'merchants', 'payouts']);

    // Pencarian global tetap menemukan menu di dalam ruang kerja.
    expect(collect($groups)->flatMap(fn ($group) => $group['items'])->pluck('key'))->toContain('masjid-screens');

    $this->get(route('staff.merchants.index'))->assertOk()->assertSee('Ruang kerja')->assertSee('Menu utama')->assertDontSee('Kelas &amp; Asrama', false);
    $this->get(route('staff.dashboard'))->assertOk()->assertSee('Kasir, katalog, stok, pencairan');
});

it('kasir hanya melihat pintu ruang kerja mart', function () {
    $this->actingAs(staff('kasir'));
    $sidebar = app(Navigation::class)->sidebar(app(Navigation::class)->staff(), 'home');
    $doors = collect($sidebar['groups'])->firstWhere('label', 'Ruang kerja')['items'] ?? [];

    expect(array_column($doors, 'label'))->not->toContain('Keuangan')->not->toContain('PPDB');
});

it('mengubah semua identitas pesantren dan langsung tampil', function () {
    Storage::fake('public');

    Livewire::test(ProfileSettings::class)
        ->set('name', 'Pesantren Uji')
        ->set('address', 'Jl. Uji 1')
        ->set('phone', '0211234')
        ->set('email', 'pesantren@uji.test')
        ->set('brand_color', '#1d3f72')
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $profile = SchoolProfile::first();
    expect($profile->name)->toBe('Pesantren Uji')
        ->and($profile->brand_color)->toBe('#1D3F72')
        ->and($profile->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($profile->logo_path);

    auth()->logout();
    $this->get(route('login'))->assertSee('Pesantren Uji')->assertSee('--color-primary:#1D3F72', false)->assertSee($profile->logo_path);
});

it('menampilkan waktu sesuai zona waktu di profil pesantren', function () {
    SchoolProfile::current()->update(['name' => 'P', 'timezone' => 'Asia/Jayapura']);
    $this->freezeTime(function () {
        Carbon::setTestNow('2026-11-07 00:00:00');
        AuditLog::create(['action' => 'uji', 'entity_type' => 'Uji']);
        $this->get(route('staff.settings.audit'))->assertOk();

        expect(config('sanad.display_timezone'))->toBe('Asia/Jayapura');
        Livewire::test(AuditLogs::class)->assertSee('07/11/2026 09.00');
    });
});

it('menolak warna yang gagal kontras dan menyarankan versi lebih gelap', function () {
    Livewire::test(ProfileSettings::class)
        ->set('name', 'P')
        ->set('brand_color', '#FFD500')
        ->call('save')
        ->assertHasErrors('brand_color')
        ->assertSee('Coba #');

    expect(SchoolProfile::first()->brand_color)->toBe('#0B5D45');
});

it('menolak berkas logo yang bukan gambar dan terlalu besar', function () {
    Storage::fake('public');

    Livewire::test(ProfileSettings::class)->set('name', 'P')->set('logo', UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'))->call('save')->assertHasErrors('logo');
    Livewire::test(ProfileSettings::class)->set('name', 'P')->set('logo', UploadedFile::fake()->image('b.png')->size(3000))->call('save')->assertHasErrors('logo');
});

it('menolak pola NIS tanpa nomor urut dan pesan error berbahasa Indonesia', function () {
    Livewire::test(ProfileSettings::class)->set('name', '')->set('nis_pattern', '{tahun}')->call('save')
        ->assertHasErrors(['name', 'nis_pattern'])
        ->assertSee('Nama wajib diisi.');
});

it('layar modul menolak dependensi dengan alasan dan tidak mengaktifkan diam-diam', function () {
    Livewire::test(ModuleSettings::class)->call('enable', 'pos')->assertSee('Aktifkan dulu: Dompet uang saku.');

    expect(Module::where('module_key', 'pos')->value('is_enabled'))->toBeFalse();
});

it('layar modul mematikan setelah konfirmasi dan menu ikut hilang', function () {
    $component = Livewire::test(ModuleSettings::class)->call('askDisable', 'finance')->assertDispatched('open-modal');
    expect(Module::where('module_key', 'finance')->value('is_enabled'))->toBeTrue();

    Module::where('module_key', 'payment_manual')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();
    $component->call('confirmDisable')->assertDispatched('toast');

    expect(Module::where('module_key', 'finance')->value('is_enabled'))->toBeFalse();
});

it('mencari modul dan menjelaskan bila tidak ada', function () {
    Livewire::test(ModuleSettings::class)->set('search', 'tahfidz')->assertSee('Tahfidz')->assertDontSee('Mart &amp; Warung', false)
        ->set('search', 'zzz')->assertSee('Tidak ada modul yang cocok');
});

it('tidak pernah mengirim kunci integrasi penuh ke layar', function () {
    Livewire::test(IntegrationSettings::class)
        ->set('midtrans_server_key', 'SB-Mid-server-rahasia1234')
        ->call('saveMidtrans')
        ->assertSet('midtrans_server_key', '')
        ->assertSee('••••1234')
        ->assertDontSee('SB-Mid-server-rahasia1234');

    $fresh = Livewire::test(IntegrationSettings::class);
    expect($fresh->html())->not->toContain('rahasia')
        ->and(Integration::where('provider', 'midtrans')->first()->credentials['server_key'])->toBe('SB-Mid-server-rahasia1234');
});

it('mempertahankan kunci lama bila isian dikosongkan', function () {
    Livewire::test(IntegrationSettings::class)->set('fonnte_token', 'token-lama-9999')->call('saveFonnte');
    Livewire::test(IntegrationSettings::class)->set('fonnte_sender', '62811')->call('saveFonnte');

    $integration = Integration::where('provider', 'fonnte')->first();
    expect($integration->credentials['token'])->toBe('token-lama-9999')->and($integration->settings['sender'])->toBe('62811');
});

it('audit log bisa difilter dan menampilkan sebelum sesudah', function () {
    $profile = SchoolProfile::create(['name' => 'Lama']);
    $profile->update(['name' => 'Baru']);
    $log = AuditLog::where('entity_type', 'SchoolProfile')->where('action', 'updated')->first();

    Livewire::test(AuditLogs::class)
        ->set('entity', 'SchoolProfile')
        ->assertSee('SchoolProfile')
        ->call('showDetail', $log->id)
        ->assertSee('Sebelum')
        ->assertSee('Lama')
        ->assertSee('Baru')
        ->set('entity', 'TidakAda')
        ->assertSee('Belum ada catatan untuk filter ini.');
});
