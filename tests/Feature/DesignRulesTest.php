<?php

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Finder\Finder;

function viewFiles(): Finder
{
    return Finder::create()->files()->in(resource_path('views'))->name('*.blade.php');
}

it('tidak memakai warna hex mentah di view', function () {
    foreach (viewFiles() as $file) {
        expect(preg_match('/#[0-9a-fA-F]{3,8}\b/', $file->getContents()))
            ->toBe(0, "Hex mentah di {$file->getRelativePathname()}");
    }
});

it('hanya memakai breakpoint yang ada di token (xs, md, lg, xl)', function () {
    foreach (viewFiles() as $file) {
        expect(preg_match('/(^|[\s"\'\[])(sm|2xl):[\w\[]/m', $file->getContents()))
            ->toBe(0, "Breakpoint tanpa token di {$file->getRelativePathname()}, kelasnya tidak pernah aktif");
    }
});

it('tidak memakai em dash di teks antarmuka', function () {
    foreach (viewFiles() as $file) {
        expect($file->getContents())->not->toContain('—', "Em dash di {$file->getRelativePathname()}");
    }
});

it('selalu menampilkan ikon dan teks pada badge status', function (string $status, string $label) {
    $html = Blade::render('<x-ui.status-badge :status="$status" />', ['status' => $status]);

    expect($html)->toContain('<svg')->toContain($label);
})->with([
    ['paid', 'Lunas'],
    ['unpaid', 'Belum bayar'],
    ['partial', 'Sebagian'],
    ['overdue', 'Terlambat'],
    ['review', 'Menunggu verifikasi'],
    ['void', 'Void'],
    ['present', 'Hadir'],
    ['excused', 'Izin'],
    ['sick', 'Sakit'],
    ['absent', 'Alpa'],
    ['late', 'Terlambat'],
    ['active', 'Aktif'],
    ['blocked', 'Diblokir'],
]);

it('menandai arsiran hanya pada status menunggu', function () {
    expect(Blade::render('<x-ui.status-badge status="review" />'))->toContain('hatch')
        ->and(Blade::render('<x-ui.status-badge status="paid" />'))->not->toContain('hatch');
});

it('menghubungkan pesan error dengan input', function () {
    $html = Blade::render('<x-ui.input label="NIS" name="nis" error="NIS sudah dipakai" />');

    expect($html)->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="field-nis-error"')
        ->toContain('role="alert"')
        ->toContain('for="field-nis"');
});

it('menonaktifkan tombol saat memproses', function () {
    $html = Blade::render('<x-ui.button loading>Simpan</x-ui.button>');

    expect($html)->toContain('disabled')->toContain('aria-busy="true"')->toContain('Memproses…')->not->toContain('Simpan');
});

it('merender ikon dengan stroke dan ukuran dari PRD', function () {
    $html = Blade::render('<x-ui.icon name="check" size="sm" />');

    expect($html)->toContain('stroke-width="1.75"')->toContain('width="16"')->toContain('aria-hidden="true"');
});
