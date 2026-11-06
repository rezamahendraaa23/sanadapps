<?php

namespace App\Modules\Core\Support;

/**
 * Daftar modul dari PRD Sistem 2.1. Kunci dan dependensi di sini mengikuti PRD apa adanya.
 */
final class ModuleCatalog
{
    /** @var array<string, array{name: string, description: string, default: bool, depends: list<string>}> */
    public const MODULES = [
        'core' => ['name' => 'Data inti', 'description' => 'Santri, wali, ustadz, kelas, asrama, tahun ajaran, pengguna dan role.', 'default' => true, 'depends' => []],
        'finance' => ['name' => 'Keuangan', 'description' => 'Jenis tagihan, tarif, diskon, tagihan otomatis, tunggakan, kas.', 'default' => true, 'depends' => ['core']],
        'payment_midtrans' => ['name' => 'Pembayaran Midtrans', 'description' => 'VA, QRIS, e-wallet, dan minimarket lewat Snap.', 'default' => false, 'depends' => ['finance']],
        'payment_manual' => ['name' => 'Transfer manual', 'description' => 'Rekening pesantren, unggah bukti, verifikasi bendahara.', 'default' => true, 'depends' => ['finance']],
        'wallet' => ['name' => 'Dompet uang saku', 'description' => 'Saldo santri, top-up, limit, ledger.', 'default' => false, 'depends' => ['finance']],
        'card' => ['name' => 'Kartu santri', 'description' => 'UID kartu dan QR, PIN, blokir, multi-akses.', 'default' => false, 'depends' => ['core']],
        'pos' => ['name' => 'Mart & Warung', 'description' => 'Kasir cashless, katalog, stok, penyaluran ke merchant.', 'default' => false, 'depends' => ['wallet']],
        'academic' => ['name' => 'Akademik', 'description' => 'Mata pelajaran, jadwal, rapor.', 'default' => true, 'depends' => ['core']],
        'attendance' => ['name' => 'Absensi', 'description' => 'Kelas, asrama, sholat, kegiatan; manual, kartu, QR, selfie.', 'default' => true, 'depends' => ['core']],
        'face_attendance' => ['name' => 'Absensi wajah', 'description' => 'Persetujuan wali, pendaftaran wajah, pencocokan, selalu ada cara lain.', 'default' => false, 'depends' => ['attendance']],
        'grading' => ['name' => 'Nilai & rapor', 'description' => 'Komponen nilai, bobot, rapor PDF.', 'default' => true, 'depends' => ['academic']],
        'tahfidz' => ['name' => 'Tahfidz & mutabaah', 'description' => 'Setoran, murojaah, target, mutabaah harian.', 'default' => false, 'depends' => ['core']],
        'exam' => ['name' => 'Ujian online', 'description' => 'Bank soal, ujian dari HP, autosave, monitor.', 'default' => false, 'depends' => ['academic']],
        'leave' => ['name' => 'Perizinan', 'description' => 'Izin pulang dan keluar, QR izin, scan gerbang.', 'default' => true, 'depends' => ['core']],
        'gate' => ['name' => 'Pos jaga gerbang', 'description' => 'Papan santri yang sedang di luar, log keluar-masuk, serah terima shift.', 'default' => false, 'depends' => ['leave']],
        'discipline' => ['name' => 'Pelanggaran & poin', 'description' => 'Aturan, poin, ambang sanksi.', 'default' => false, 'depends' => ['core']],
        'health' => ['name' => 'Kesehatan (UKS)', 'description' => 'Catatan kesehatan santri dengan akses terbatas.', 'default' => false, 'depends' => ['core']],
        'ppdb' => ['name' => 'PPDB', 'description' => 'Pendaftaran online, dokumen, seleksi, daftar ulang.', 'default' => false, 'depends' => ['core']],
        'notification_wa' => ['name' => 'Notifikasi WhatsApp', 'description' => 'Pengiriman lewat Fonnte, template, antrean, log.', 'default' => false, 'depends' => ['core']],
        'portal_wali' => ['name' => 'Portal wali', 'description' => 'Aplikasi web untuk wali santri.', 'default' => true, 'depends' => ['core']],
        'portal_santri' => ['name' => 'Portal santri', 'description' => 'Aplikasi web untuk santri.', 'default' => true, 'depends' => ['core']],
        'analytics' => ['name' => 'Analitik risiko', 'description' => 'Skor risiko dari tunggakan, kehadiran, nilai, pelanggaran.', 'default' => false, 'depends' => ['core']],
        'alumni' => ['name' => 'Alumni', 'description' => 'Data alumni dan kelulusan.', 'default' => false, 'depends' => ['core']],
        'inventory' => ['name' => 'Inventaris asrama', 'description' => 'Barang, mutasi, kondisi.', 'default' => false, 'depends' => ['core']],
        'duty_roster' => ['name' => 'Jadwal piket', 'description' => 'Piket santri dan ustadz.', 'default' => false, 'depends' => ['core']],
        'export_dapodik' => ['name' => 'Ekspor Dapodik/EMIS', 'description' => 'Ekspor file sesuai template.', 'default' => false, 'depends' => ['core']],
    ];

    /**
     * @return list<string>
     */
    public static function dependents(string $key): array
    {
        return array_keys(array_filter(self::MODULES, fn (array $module) => in_array($key, $module['depends'], true)));
    }
}
