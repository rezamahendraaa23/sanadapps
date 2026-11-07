<?php

namespace App\Modules\Academic\Face;

/**
 * Batas ke teknologi pencocokan wajah (PRD Sistem, Catatan D8). SANAD hanya menyimpan template
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
     * True bila wajah diolah di browser, jadi halaman kamera memuat modelnya dan mengirim angka, bukan gambar.
     */
    public function runsInBrowser(): bool;

    /**
     * @return array{upper: float, lower: float}
     */
    public function defaultThresholds(): array;

    /**
     * @param  list<FaceInput|string>  $inputs  Beberapa pengambilan dari sudut berbeda.
     * @return FaceTemplate Yang disimpan SANAD; tidak pernah berisi foto.
     *
     * @throws FaceQualityException
     */
    public function enroll(array $inputs): FaceTemplate;

    /**
     * Identifikasi 1:N terhadap santri peserta sesi.
     *
     * @param  array<int, array<int|string, mixed>>  $templates  id santri ke template hasil enroll().
     * @return FaceMatch|null Kandidat terbaik, null bila tidak ada kandidat.
     *
     * @throws FaceQualityException
     */
    public function identify(FaceInput|string $input, array $templates): ?FaceMatch;

    public function isLive(string $challenge, FaceInput $input): bool;
}
