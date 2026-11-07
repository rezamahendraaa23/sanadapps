<?php

namespace App\Modules\Notification\Support;

/**
 * Pemicu dari PRD Sistem 9 yang sudah aktif. Pemicu modul lain ditambah di tahapnya.
 */
final class NotificationCatalog
{
    /** @var array<string, array{label: string, critical: bool, default?: bool, module?: string}> */
    public const CATEGORIES = [
        'billing' => ['label' => 'Tagihan dan pembayaran', 'critical' => true],
        'card' => ['label' => 'Kartu diblokir atau hilang', 'critical' => true, 'module' => 'card'],
        'wallet' => ['label' => 'Top-up dan saldo menipis', 'critical' => false, 'module' => 'wallet'],
        'spend' => ['label' => 'Setiap belanja kartu', 'critical' => false, 'default' => false, 'module' => 'pos'],
        'announcement' => ['label' => 'Pengumuman pesantren', 'critical' => false],
    ];

    /** @var array<string, array{label: string, category: string|null, variables: list<string>, body: string}> */
    public const EVENTS = [
        'invoice_issued' => [
            'label' => 'Tagihan terbit',
            'category' => 'billing',
            'variables' => ['nama_wali', 'nama_santri', 'periode', 'total', 'jatuh_tempo', 'link'],
            'body' => "Assalamu'alaikum {nama_wali}.\nTagihan {periode} untuk {nama_santri} sudah terbit: {total}, jatuh tempo {jatuh_tempo}.\nBayar di sini: {link}",
        ],
        'payment_reminder' => [
            'label' => 'Pengingat tagihan',
            'category' => 'billing',
            'variables' => ['nama_wali', 'nama_santri', 'periode', 'sisa', 'jatuh_tempo', 'link'],
            'body' => "Assalamu'alaikum {nama_wali}.\nTagihan {periode} untuk {nama_santri} masih {sisa}, jatuh tempo {jatuh_tempo}.\nBayar di sini: {link}\nAbaikan bila sudah membayar.",
        ],
        'payment_success' => [
            'label' => 'Pembayaran berhasil',
            'category' => 'billing',
            'variables' => ['nama_wali', 'nama_santri', 'nominal', 'metode', 'link'],
            'body' => "Assalamu'alaikum {nama_wali}.\nPembayaran {nominal} untuk {nama_santri} lewat {metode} sudah kami terima. Jazakumullahu khairan.\nKwitansi: {link}",
        ],
        'payment_failed' => [
            'label' => 'Pembayaran gagal atau kedaluwarsa',
            'category' => 'billing',
            'variables' => ['nama_wali', 'nama_santri', 'alasan', 'link'],
            'body' => "Assalamu'alaikum {nama_wali}.\nPembayaran untuk {nama_santri} belum berhasil: {alasan}\nBayar ulang di sini: {link}",
        ],
        'proof_submitted' => [
            'label' => 'Bukti transfer masuk (ke bendahara)',
            'category' => null,
            'variables' => ['nama_santri', 'nominal', 'link'],
            'body' => "Bukti transfer baru dari wali {nama_santri} sebesar {nominal}.\nVerifikasi: {link}",
        ],
        'proof_rejected' => [
            'label' => 'Bukti transfer ditolak',
            'category' => 'billing',
            'variables' => ['nama_wali', 'nama_santri', 'alasan', 'link'],
            'body' => "Assalamu'alaikum {nama_wali}.\nBukti transfer untuk {nama_santri} belum bisa kami terima: {alasan}\nUnggah ulang di sini: {link}",
        ],
        'topup_success' => [
            'label' => 'Top-up berhasil',
            'category' => 'wallet',
            'variables' => ['nama_wali', 'nama_santri', 'nominal', 'saldo', 'link'],
            'body' => "Assalamu'alaikum {nama_wali}.\nTop-up {nominal} untuk {nama_santri} berhasil. Saldo sekarang {saldo}.\nRiwayat: {link}",
        ],
        'wallet_low' => [
            'label' => 'Saldo di bawah ambang',
            'category' => 'wallet',
            'variables' => ['nama_wali', 'nama_santri', 'saldo', 'link'],
            'body' => "Assalamu'alaikum {nama_wali}.\nSaldo {nama_santri} tinggal {saldo}.\nTop-up di sini: {link}",
        ],
        'wallet_spend' => [
            'label' => 'Belanja kartu',
            'category' => 'spend',
            'variables' => ['nama_wali', 'nama_santri', 'nominal', 'merchant', 'saldo', 'link'],
            'body' => "{nama_santri} belanja {nominal} di {merchant}. Sisa saldo {saldo}.\nRiwayat: {link}",
        ],
        'card_blocked' => [
            'label' => 'Kartu diblokir atau hilang',
            'category' => 'card',
            'variables' => ['nama_wali', 'nama_santri', 'alasan', 'link'],
            'body' => "Assalamu'alaikum {nama_wali}.\nKartu {nama_santri} sudah diblokir: {alasan}. Saldo tetap aman di dompet.\nDetail: {link}",
        ],
        'stock_low' => [
            'label' => 'Stok menipis (ke pengelola)',
            'category' => null,
            'variables' => ['merchant', 'produk', 'sisa', 'link'],
            'body' => "Stok {produk} di {merchant} tinggal {sisa}.\nCek stok: {link}",
        ],
        'payout_status' => [
            'label' => 'Status pencairan (ke pengelola)',
            'category' => null,
            'variables' => ['merchant', 'nominal', 'status', 'link'],
            'body' => "Pencairan {merchant} sebesar {nominal}: {status}.\nDetail: {link}",
        ],
        'announcement' => [
            'label' => 'Pengumuman',
            'category' => 'announcement',
            'variables' => ['judul', 'isi'],
            'body' => "{judul}\n\n{isi}",
        ],
    ];

    /**
     * @param  array<string, string|int|null>  $variables
     */
    public static function render(string $body, array $variables): string
    {
        return strtr($body, collect($variables)->mapWithKeys(fn ($value, $key) => ['{'.$key.'}' => (string) $value])->all());
    }

    /**
     * @return list<string> Variabel di teks yang tidak dikenal event ini.
     */
    public static function unknownVariables(string $eventKey, string $body): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $body, $matches);

        return array_values(array_diff(array_unique($matches[1]), self::EVENTS[$eventKey]['variables']));
    }
}
