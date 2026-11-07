<?php

namespace App\Modules\Letters\Support;

use App\Modules\Letters\Models\LetterTemplate;

/**
 * Template awal yang bisa diubah pesantren. Dibuat sekali saat modul pertama dibuka.
 */
final class DefaultTemplates
{
    private const STUDENT_BLOCK = "Nama: {nama_santri}\nNIS: {nis}\nKelas: {kelas}\nAsrama: {asrama}";

    public static function ensure(): void
    {
        if (LetterTemplate::query()->exists()) {
            return;
        }

        foreach (self::all() as $template) {
            LetterTemplate::create($template);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'Surat keterangan sakit',
                'code' => 'SKS',
                'subject' => 'Surat Keterangan Sakit',
                'needs_student' => true,
                'fields' => [['key' => 'keluhan', 'label' => 'Keluhan atau diagnosis singkat'], ['key' => 'lama_istirahat', 'label' => 'Lama istirahat (hari)']],
                'body' => "Yang bertanda tangan di bawah ini, {jabatan} {nama_lembaga}, menerangkan bahwa:\n\n".self::STUDENT_BLOCK."\n\nbenar dalam keadaan sakit ({keluhan}) dan memerlukan istirahat selama {lama_istirahat} hari terhitung sejak tanggal surat ini.\n\nDemikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.",
            ],
            [
                'name' => 'Surat izin santri',
                'code' => 'SIS',
                'subject' => 'Surat Izin Santri',
                'needs_student' => true,
                'fields' => [['key' => 'keperluan', 'label' => 'Keperluan'], ['key' => 'tanggal_mulai', 'label' => 'Tanggal mulai'], ['key' => 'tanggal_kembali', 'label' => 'Tanggal kembali']],
                'body' => "Yang bertanda tangan di bawah ini, {jabatan} {nama_lembaga}, memberikan izin kepada:\n\n".self::STUDENT_BLOCK."\n\nuntuk meninggalkan pondok guna {keperluan}, mulai {tanggal_mulai} dan wajib kembali pada {tanggal_kembali}.\n\nSelama izin, santri tetap menjaga adab dan nama baik pondok. Demikian surat izin ini dibuat untuk dipergunakan sebagaimana mestinya.",
            ],
            [
                'name' => 'Pemberitahuan kegiatan kepada RT/RW',
                'code' => 'SPK',
                'subject' => 'Pemberitahuan dan Permohonan Izin Kegiatan',
                'needs_student' => false,
                'fields' => [['key' => 'nama_acara', 'label' => 'Nama kegiatan'], ['key' => 'hari_tanggal', 'label' => 'Hari dan tanggal'], ['key' => 'waktu', 'label' => 'Waktu'], ['key' => 'tempat', 'label' => 'Tempat']],
                'body' => "Assalamu'alaikum warahmatullahi wabarakatuh.\n\nDengan hormat, bersama surat ini kami memberitahukan bahwa {nama_lembaga} akan menyelenggarakan kegiatan:\n\nNama kegiatan: {nama_acara}\nHari dan tanggal: {hari_tanggal}\nWaktu: {waktu}\nTempat: {tempat}\n\nSehubungan dengan kegiatan tersebut, kami mohon izin dan dukungan Bapak. Kami akan menjaga ketertiban, kebersihan, dan kenyamanan lingkungan selama kegiatan berlangsung.\n\nAtas perhatian dan kerja samanya, kami ucapkan terima kasih.\n\nWassalamu'alaikum warahmatullahi wabarakatuh.",
            ],
            [
                'name' => 'Permohonan sumbangan kegiatan',
                'code' => 'SPS',
                'subject' => 'Permohonan Dukungan Kegiatan',
                'needs_student' => false,
                'fields' => [['key' => 'nama_acara', 'label' => 'Nama kegiatan'], ['key' => 'hari_tanggal', 'label' => 'Hari dan tanggal'], ['key' => 'tempat', 'label' => 'Tempat'], ['key' => 'rekening', 'label' => 'Rekening atau cara menyumbang'], ['key' => 'narahubung', 'label' => 'Narahubung']],
                'body' => "Assalamu'alaikum warahmatullahi wabarakatuh.\n\nDengan hormat, dalam rangka {nama_acara} yang insya Allah dilaksanakan pada {hari_tanggal} di {tempat}, kami memohon dukungan Bapak/Ibu dalam bentuk sumbangan dana maupun barang sesuai kemampuan.\n\nSumbangan dapat disalurkan melalui {rekening}. Informasi lebih lanjut dapat menghubungi {narahubung}.\n\nSemoga Allah membalas kebaikan Bapak/Ibu dengan pahala yang berlipat. Atas perhatian dan kepeduliannya, kami ucapkan terima kasih.\n\nWassalamu'alaikum warahmatullahi wabarakatuh.",
            ],
        ];
    }
}
