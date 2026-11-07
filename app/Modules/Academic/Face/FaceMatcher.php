<?php

namespace App\Modules\Academic\Face;

/**
 * Batas ke teknologi pencocokan wajah yang belum diputuskan (PRD Sistem, Catatan D8). Implementasinya bisa
 * adapter ke perangkat jadi (Jalur A) atau layanan sendiri (Jalur B), jadi SANAD hanya menyimpan template
 * yang dikembalikan dan tidak tahu cara pencocokannya.
 */
interface FaceMatcher
{
    /**
     * False berarti absen wajah tidak boleh berjalan; pengguna diarahkan ke kartu atau manual.
     */
    public function available(): bool;

    public function modelVersion(): string;

    /**
     * @param  list<string>  $images  Isi berkas foto dari beberapa sudut.
     * @return FaceTemplate Yang disimpan SANAD; tidak pernah berisi foto.
     *
     * @throws FaceQualityException Ada foto yang tidak cukup baik.
     */
    public function enroll(array $images): FaceTemplate;

    /**
     * Identifikasi 1:N terhadap santri peserta sesi.
     *
     * @param  array<int, array<int|string, mixed>>  $templates  id santri ke template hasil enroll().
     * @return FaceMatch|null Kandidat terbaik, null bila tidak ada kandidat.
     *
     * @throws FaceQualityException Gambar tidak cukup baik untuk dicocokkan.
     */
    public function identify(string $image, array $templates): ?FaceMatch;

    public function isLive(string $challenge, string $firstFrame, string $secondFrame): bool;
}
