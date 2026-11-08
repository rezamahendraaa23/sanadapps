<?php

use App\Livewire\Preview\SampleTable;
use App\Modules\Core\Models\SchoolProfile;
use App\Support\Navigation;
use Livewire\Livewire;

it('membuka halaman awal tanpa nama pesantren', function () {
    config(['sanad.school.name' => null]);

    $this->get('/')->assertOk()->assertSee('SANAD')->assertSee('Pesantren Digital Ecosystem');
});

it('memakai nama dan warna pesantren dari pengaturan', function () {
    config(['sanad.school.name' => 'Pesantren Uji', 'sanad.school.brand_color' => '#1D3F72']);

    $this->get('/')->assertOk()->assertSee('Pesantren Uji')->assertSee('--color-primary:#1D3F72', false);
});

it('mengabaikan warna pesantren yang gagal uji kontras', function () {
    config(['sanad.school.brand_color' => '#FFD500']);

    $this->get('/')->assertOk()->assertDontSee('--color-primary:', false);
});

it('menampilkan semua pratinjau di lingkungan pengembangan', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/_pratinjau', '/_pratinjau/staf', '/_pratinjau/portal/wali', '/_pratinjau/portal/santri', '/_pratinjau/portal/ustadz', '/_pratinjau/portal/musyrif']);

it('menyembunyikan pratinjau di produksi', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/_pratinjau')->assertNotFound();
});

it('menolak peran portal yang tidak dikenal', function () {
    $this->get('/_pratinjau/portal/tamu')->assertNotFound();
});

it('tidak menampilkan menu yang rutenya belum ada', function () {
    $keys = collect(app(Navigation::class)->staff())->flatMap(fn ($group) => $group['items'])->pluck('key');

    expect($keys)->toContain('home')->not->toContain('exams')
        ->and(collect(app(Navigation::class)->portal('wali'))->pluck('key')->all())->toBe(['home', 'invoices', 'progress', 'more']);
});

it('memakai isian profil pesantren di atas nilai awal', function () {
    config(['sanad.school.name' => 'Dari Config']);
    SchoolProfile::create(['name' => 'Dari Pengaturan', 'brand_color' => '#1D3F72']);

    $this->get('/')->assertOk()->assertSee('Dari Pengaturan')->assertDontSee('Dari Config')->assertSee('--color-primary:#1D3F72', false);
});

it('membatasi navigasi portal maksimal lima item', function (string $role) {
    expect(config("sanad.navigation.portal.{$role}"))->toHaveCount(5);
})->with(['wali', 'santri', 'ustadz', 'musyrif']);

it('memakai module_key yang terdaftar di PRD untuk semua menu', function () {
    $known = ['core', 'finance', 'payment_midtrans', 'payment_manual', 'wallet', 'card', 'pos', 'academic', 'attendance',
        'face_attendance', 'grading', 'tahfidz', 'exam', 'leave', 'gate', 'discipline', 'health', 'ppdb', 'notification_wa',
        'portal_wali', 'portal_santri', 'analytics', 'alumni', 'inventory', 'duty_roster', 'export_dapodik', 'masjid_prayer', 'masjid_display', 'masjid_public',
        // Tambahan pemilik 8 November 2026.
        'letters', 'curriculum'];

    $items = collect(config('sanad.navigation.staff'))->flatMap(fn ($group) => $group['items'])
        ->merge(collect(config('sanad.navigation.portal'))->flatten(1));

    expect($items->pluck('module')->unique()->diff($known))->toBeEmpty();
});

it('mengurutkan dan mencari di tabel contoh', function () {
    Livewire::test(SampleTable::class)
        ->assertSeeInOrder(['Santri Contoh 01', 'Santri Contoh 06'])
        ->call('sortBy', 'name')
        ->assertSeeInOrder(['Santri Contoh 06', 'Santri Contoh 01'])
        ->set('search', 'contoh 03')
        ->assertSee('Santri Contoh 03')
        ->assertDontSee('Santri Contoh 04')
        ->set('search', 'tidak ada')
        ->assertSee('Tidak ada santri contoh yang cocok');
});

it('menampilkan keadaan gagal dan bisa memuat ulang', function () {
    Livewire::test(SampleTable::class)
        ->call('simulateFailure')
        ->assertSee('Data contoh gagal dimuat')
        ->call('retry')
        ->assertDontSee('Data contoh gagal dimuat')
        ->assertDispatched('toast');
});
