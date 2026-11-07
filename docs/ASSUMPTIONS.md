# Asumsi dan keputusan SANAD

Catatan keputusan saat dokumen sumber bertentangan atau punya celah. Urutan sumber: PRD Sistem menang untuk data dan aturan, PRD UI/UX menang untuk tampilan dan interaksi.

## Keputusan dari pemilik proyek

| Topik | Keputusan | Tanggal |
|---|---|---|
| Identitas pesantren (D1) | Belum ada nama tetap. Nama, logo, alamat, kontak, dan warna semuanya diatur admin di Pengaturan (dibangun di Tahap 1). Selama kosong, aplikasi tampil sebagai SANAD | 7 Nov 2026 |
| Font | Permintaan pemilik: lucu, mudah dibaca, tidak berlebihan, tidak kaku. Fredoka (judul dan angka hero), Nunito (UI dan isi), Amiri (Arab utama), Noto Naskh Arabic (Arab cadangan). Menggantikan Fraunces dan Figtree di PRD UI/UX 2.3 | 7 Nov 2026 |
| Database | PostgreSQL 16, versi terkelola untuk produksi. Menggantikan MySQL 8 di PRD dan master prompt | 7 Nov 2026 |
| Benturan aturan antislop | Pemilik menyerahkan ke pengembang. Lucide tetap dipakai karena diminta PRD: satu set konsisten dan ringan. Em dash tidak dipakai di antarmuka | 7 Nov 2026 |

## Belum diputuskan

| Topik | Status |
|---|---|
| Lingkungan lokal | Docker Desktop belum terpasang dan WSL2 belum aktif. Sementara memakai PHP 8.3 dan PostgreSQL 16 portabel di `%LOCALAPPDATA%\sanad-tools`. Horizon butuh `pcntl`, jadi tidak bisa berjalan di Windows langsung |
| Penyedia PostgreSQL terkelola | Bagian dari D6 (hosting), belum dipilih |

## Risiko yang perlu diukur

| Topik | Status |
|---|---|
| Tombol sebelum skrip siap | Tombol Livewire tidak bereaksi bila ditekan sebelum skrip selesai dimuat. Di HP dan koneksi lambat ini bisa terasa. Ukur di Tahap 13, pertimbangkan menonaktifkan tombol sampai halaman siap |

## Asumsi

| # | Asumsi | Alasan |
|---|---|---|
| A1 | Nominal uang memakai `bigint` rupiah, bukan `decimal` seperti di master prompt | PRD Sistem 6.1 menang untuk data. Tetap tanpa float |
| A2 | "Kartu biru solid" di UI/UX 2.1 dibaca sebagai kartu hijau primary | Sisa versi lama; warna utama sudah `#0B5D45` |
| A3 | Radius mengikuti UI/UX 2.4 (kartu 14, modal 18), bukan 7 (12, 16) | 2.4 adalah sumber design system |
| A4 | Livewire tetap versi 3 walau versi 4 sudah rilis | Stack dikunci di PRD |
| A5 | Badge menunggu memakai teks tinta di atas arsiran, bukan teks `warning` | `warning` di atas `warning-soft` hanya 4,51:1, turun di bawah 4,5:1 bila diberi arsiran |
| A6 | Nilai angka hero turun sampai 28 px di kolom sempit, di bawah rentang 36 sampai 44 px di PRD | Nominal panjang di kolom sempit meluber bila dipaksa 36 px |
| A7 | Halaman `/_pratinjau` hanya tersedia di lingkungan `local` dan `testing` | Dipakai memeriksa komponen sebelum ada layar sungguhan; berisi data contoh |
| A8 | Preload font belum dipasang; font memakai `font-display: swap` | Nama file font di-hash Vite. Ditinjau lagi saat hardening Tahap 13 |
| A9 | Fitur topbar (pencarian, lonceng, profil) dibuat di Tahap 1 bersama data dan login | Tanpa data dan akun, kontrol itu hanya tombol mati (R-26) |
| A11 | Test memakai PostgreSQL, bukan SQLite | Indeks unik parsial, `jsonb`, trigger, dan `FOR UPDATE` tidak bisa diuji jujur di SQLite |
| A12 | Pengembangan lokal memakai PostgreSQL 16 portabel di port 5433, bukan PostgreSQL 18 yang sudah ada di port 5432 | Versinya sama dengan target PRD, dan instance 18 tidak terganggu |
| A13 | Endpoint REST `/api/v1` di PRD Sistem 7 untuk Tahap 1 tidak dibuat dulu; layar Livewire memanggil Action yang sama | Belum ada klien API. Endpoint perangkat, webhook, dan ujian dibuat di tahapnya |
| A14 | Tambahan di luar PRD: tabel `otp_codes` dan `imports`, kolom `setup_dismissed_at`, serta kolom 2FA Fortify | Dibutuhkan OTP, status import, dan 2FA; PRD menyebut fiturnya tanpa rincian tabel |
| A15 | Kolom `integrations.credentials` bertipe `text`, bukan `jsonb` | Isi terenkripsi bukan JSON yang valid |
| A16 | OTP memakai `LogOtpSender` sampai Fonnte selesai di Tahap 3; di produksi pengirim ini menolak, jadi kode tidak pernah masuk log | Keputusan urutan kerja di master prompt |
| A17 | Ustadz melihat santri lewat wali kelas sampai penugasan mengajar ada di Tahap 6 | Tabel penugasan mengajar belum ada |
| A18 | SE-03 Tahap 1 hanya menyimpan kredensial. Tes koneksi, URL webhook, dan kanal menyusul di Tahap 3; rekening transfer manual di Tahap 2 | Butuh klien Midtrans, Fonnte, dan tabel `bank_accounts` dari tahap itu |
| A19 | Kartu modul memakai tombol Aktifkan dan Matikan berlabel, bukan toggle | Status tertulis jelas dan aksi punya kata kerja |
| A20 | Santri hasil import belum punya sandi; admin membuatnya dari detail santri | Hash per baris melewati batas 30 detik untuk 200 baris dan daftar sandi massal berisiko bocor |
| A21 | SET-03 di Tahap 1 hanya santri dan wali. Saldo dompet awal menyusul di Tahap 4, tunggakan awal di Tahap 2 | Tabelnya baru ada di tahap itu |
| A22 | Opsi kirim WA pada pengumuman belum ditampilkan; pengumuman hanya tayang di portal sampai Tahap 3 | Pengiriman Fonnte belum ada, jadi kontrolnya akan mati |
| A23 | Checklist setup baru 6 dari 7 langkah PRD UI/UX 5.6; langkah jenis tagihan ditambah di Tahap 2 | Layar jenis tagihan belum ada, jadi tautannya akan mati |
| A24 | Status backup di Kesehatan Sistem menyusul di Tahap 13 | Penjadwalan backup dibuat di tahap itu |
| A25 | Pencarian topbar baru mencari santri dan NIS; nomor invoice menyusul di Tahap 2 | Tabel invoice belum ada |
| A26 | Pencarian topbar disembunyikan di bawah 1024 px; di HP cari lewat halaman Santri | Topbar sempit, sesuai aturan satu aksi utama |
| A27 | Detail santri baru punya tab Profil, Wali, dan Riwayat kelas; tab lain di PRD UI/UX A-03 ditambah di tahap modulnya | Datanya belum ada |
| A28 | Hanya role `bendahara` yang bisa memverifikasi transfer dan mencatat pengembalian; admin tidak, sesuai matriks PRD Sistem 10 (admin RW, bendahara RWA). Bila satu orang memegang keduanya, beri dua role | Mengikuti PRD |
| A29 | Tambahan kolom di luar PRD: `fee_rates.scope_level` (jenjang berupa teks, sedangkan `scope_id` angka), `invoice_items.is_late_fee`, `invoices.created_by`, `payments.refund_reason`, `payments.created_by`, serta tabel `document_counters` | Dibutuhkan fitur PRD; nama dan kolom yang ada tidak diubah |
| A30 | `invoices.student_id` boleh kosong untuk tagihan PPDB; kolom `applicant_id` diberi relasi saat Tahap 11 | Pendaftar belum menjadi santri |
| A31 | Void melepas `period_key` item agar periode itu bisa ditagih ulang | Indeks unik PRD akan memblokir tagihan ulang setelah salah generate |
| A32 | Jenis `one_time` memakai kunci periode `sekali`, `custom` memakai kunci bulan; denda memakai kunci `denda:<id item>` | PRD hanya merinci bulanan, tahunan, dan manual |
| A33 | Denda dihitung per item yang jenisnya punya aturan denda, sekali saja, setelah lewat `late_fee_after_days` dari jatuh tempo invoice | Mengikuti contoh PRD Sistem 8.2 |
| A34 | Pembayaran sebagian lewat portal wali belum dibuka; cicilan dicatat bendahara lewat tunai. Tampilan bayar sebagian per item juga belum ada | Alur wali di PRD UI/UX memilih tagihan utuh |
| A35 | Foto bukti dikompres di server (sisi terpanjang 1600 px), bukan di klien | Hasilnya sama dan tidak bergantung kemampuan HP |
| A36 | Halaman periksa kwitansi publik tidak menampilkan nama santri, hanya nomor, nominal, tanggal, dan status | Data anak disaring; alamatnya tidak bisa ditebak tetapi bisa tersebar |
| A37 | Kirim pengingat WA di layar Tunggakan dan rekonsiliasi Midtrans dibuat di Tahap 3 | Butuh Fonnte dan Midtrans |
| A38 | Santri belum punya layar tagihan; portal santri baru menampilkan keuangan saat layar S-08 dan S-09 dibuat | Fokus Tahap 2 pada wali dan bendahara |
| A10 | `CLAUDE.md` dan `AGENTS.md` bawaan template Laravel diganti dengan catatan proyek; Laravel Boost tidak dipasang | Bukan bagian stack PRD |
