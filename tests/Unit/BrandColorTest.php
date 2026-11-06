<?php

use App\Support\BrandColor;

it('menghitung kontras warna bawaan terhadap putih', function () {
    expect(round(BrandColor::contrastWithWhite('#0B5D45'), 2))->toBe(7.88);
});

it('menolak warna terang yang gagal 4,5:1', function () {
    expect(BrandColor::isAccessible('#FFD500'))->toBeFalse()
        ->and(BrandColor::isAccessible('#0B5D45'))->toBeTrue();
});

it('menyarankan versi lebih gelap yang lolos kontras', function () {
    $suggestion = BrandColor::suggestAccessible('#FFD500');

    expect($suggestion)->not->toBe('#FFD500')
        ->and(BrandColor::isAccessible($suggestion))->toBeTrue();
});

it('tidak mengubah warna yang sudah lolos saat menyarankan', function () {
    expect(BrandColor::suggestAccessible('#0b5d45'))->toBe('#0B5D45');
});

it('hanya menghasilkan CSS untuk warna kustom yang lolos', function (?string $color, bool $hasCss) {
    expect(BrandColor::cssOverrides($color) !== null)->toBe($hasCss);
})->with([
    'kosong' => [null, false],
    'bawaan' => ['#0B5D45', false],
    'gagal kontras' => ['#FFD500', false],
    'format salah' => ['merah; } body { display: none', false],
    'kustom lolos' => ['#1D3F72', true],
]);
