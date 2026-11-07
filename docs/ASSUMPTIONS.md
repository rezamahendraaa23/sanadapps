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
| A39 | Midtrans memakai HTTP client Laravel di balik `PaymentGateway`, bukan paket `midtrans/midtrans-php` di PRD Sistem 4.1 | Paket itu memakai konfigurasi global statis dan tidak bisa diuji dengan `Http::fake`; endpoint dan tanda tangan sama dengan dokumentasi resmi |
| A40 | Biaya admin mode `passed` berupa nominal tetap per transaksi yang diatur admin | Kanal baru diketahui setelah Snap terbuka, sedangkan `gross_amount` harus pasti sebelumnya |
| A41 | Tambahan kolom `payments.needs_review`, `review_note`, `last_synced_at` | PRD 8.5 meminta tanda needs_review tetapi kolom statusnya tidak memuat nilai itu |
| A42 | Uang yang masuk setelah pembayaran ditutup tetap dilunasi bila tagihan masih terbuka; bila sudah lunas lewat cara lain ditandai untuk ditinjau | Uangnya nyata dan tidak boleh hilang tanpa jejak |
| A43 | Kode kanal Snap memakai `other_qris` untuk QRIS dan huruf kecil untuk minimarket | Dokumentasi Midtrans tidak merinci semua kode; perlu dipastikan dengan akun sandbox |
| A44 | `SANAD_FAKE_MIDTRANS` mengganti Midtrans dengan tiruan untuk uji lokal, diabaikan di produksi | Uji alur penuh tanpa kunci sandbox |
| A45 | Webhook status Fonnte dilindungi token rahasia di alamatnya | Dokumentasi Fonnte tidak menyebut tanda tangan atau rahasia webhook |
| A46 | Nilai `state` Fonnte yang dipetakan: delivered, read, played menjadi sampai; failed, invalid, expired menjadi gagal; lainnya diabaikan | Dokumentasi tidak merinci nilainya, perlu dipastikan dengan akun sungguhan |
| A47 | Tambahan kolom `notification_logs.dedupe_key` | Mencegah pengingat dan pesan pembayaran terkirim dua kali bila job berjalan ulang |
| A48 | Pemicu WA aktif di Tahap 3 baru yang modulnya sudah ada; pemicu dompet, izin, gerbang, dan lainnya ditambah di tahapnya | Modulnya belum dibangun |
| A49 | Horizon terpasang tetapi belum pernah dijalankan; pengujian antrean memakai `Queue::fake` dan job dipanggil langsung | Butuh Redis dan `pcntl`, keduanya tidak ada di Windows tanpa WSL2 atau Docker |
| A50 | Pindah halaman memakai `wire:navigate`; tautan unduhan, alamat luar, dan layar masuk tetap muat penuh. Tautan halaman baru wajib memakai atribut ini | Reza meminta perpindahan terasa langsung. Jangan memakai `wire:navigate.hover`: salinan halaman disimpan sampai 30 detik, sehingga tagihan yang baru dibuat sempat tampak belum ada |
| A51 | Tambahan kolom `wallet_transactions.direction` (`credit`, `debit`) | PRD menyatakan arah dari jenis, tetapi `refund` dan `adjustment` bisa dua arah: pembatalan belanja menambah saldo, penarikan saldo (WAL-05) mengurangi |
| A52 | Tambahan tabel `merchant_user` untuk kasir per merchant | PRD hanya punya `pic_user_id`, padahal POS-01 meminta tiap merchant punya kasir sendiri |
| A53 | QR tercetak di kartu tetap dan tidak bisa untuk belanja; hanya QR dinamis di HP yang bisa | QR cetak mudah difoto, sedangkan CRD-05 menolak QR lama |
| A54 | Scope kartu `library` dan `meal` selalu ditolak | PRD 2.1 belum punya modul untuk keduanya, dan CRD-02 meminta scope tanpa modul aktif ditolak |
| A55 | Tahap 4 memuat merchant, produk, kasir, struk, void, komisi. Stok, impor, aturan wali per merchant, penyaluran, dan laporan menyusul di Tahap 5 | Pembagian tahap di master prompt |
| A56 | Top-up dan penarikan saldo dicatat di kas sebagai kategori sendiri | Uang fisik atau transfer masuk ke pesantren saat top-up, jadi harus terlihat di kas |
| A57 | Tambahan kolom `stock_movements.stock_after`, `pos_order_items.cost_price`, `pos_order_items.category_id`, `merchant_payouts.reject_reason`, izin `merchants.own` dan `payouts.approve` | Margin harus memakai harga modal saat terjual, bukan harga modal terbaru; batas kategori tetap benar walau produk dipindah kategori |
| A58 | Wali tidak mengatur ambang PIN sendiri, walau W-15 menyebutnya | PRD Sistem hanya punya ambang di sistem dan merchant; ambang per santri belum ada kolomnya. Perlu dikonfirmasi bila memang dibutuhkan |
| A59 | Stok di berkas impor dianggap hasil hitung, selisihnya menjadi mutasi `adjust` | Stok tidak boleh berubah tanpa jejak, dan berkas yang sama aman diimpor ulang |
| A60 | Kamera barcode memakai `BarcodeDetector` bawaan browser; bila tidak didukung tombolnya tidak tampil dan kasir memakai reader atau ketik | Tanpa pustaka tambahan. Tersedia di Chrome Android, tidak di Safari dan Firefox |
| A61 | Absen kartu dan QR: santri mulai berstatus alpa sampai memindai, bukan hadir | PRD 8.9 menyebut sesi baru default hadir, tetapi juga menyebut tidak memindai berarti alpa. Default hadir hanya untuk absen manual |
| A62 | Simpan absensi sekaligus menutup sesi; masih bisa dikoreksi | U-03 hanya punya tombol Simpan, tidak ada tombol Tutup terpisah |
| A63 | PDF rapor dibuat saat diunduh dari angka yang sudah dibekukan di `report_card_items`, kolom `pdf_path` belum dipakai | Tidak perlu penyimpanan berkas; isi tetap sama karena itemnya tidak berubah setelah terbit |
| A64 | Nilai dinormalkan ke skala 100 memakai nilai maksimum penilaian; bobot dibagi hanya dari komponen yang ada penilaiannya | Rumus PRD 8.10 tidak menyebut penilaian dengan maksimum selain 100 atau komponen yang kosong |
| A65 | Izin aktif belum mengubah alpa menjadi izin saat absensi | Modul perizinan baru ada di Tahap 7, disambungkan di tahap itu |
| A66 | Tinjauan pencatatan manual disimpan di tabel `gate_log_reviews`, bukan kolom di `gate_logs` | `gate_logs` wajib tidak bisa diubah (PRD 8.11b), padahal status tinjauan berubah |
| A67 | Tambahan kolom `leave_requests.first_approved_by` dan `late_notified_at` | Dua tingkat persetujuan (LEV-02) dan pemberitahuan terlambat sekali saja |
| A68 | Santri yang kembali terlambat tetap berstatus `late`, papan memakai `returned_at IS NULL` untuk membedakan yang masih di luar | PRD menyatakan papan membaca `out` dan `late`, tetapi juga bahwa masuk terlambat berstatus `late` dan hilang dari papan |
| A69 | Override darurat cukup lewat akun musyrif atau admin yang sedang masuk, tanpa PIN terpisah | K-04 menyebut PIN musyrif, tetapi PRD Sistem tidak punya kolom PIN untuk staf. Penjaga tetap tidak bisa melakukannya |
| A70 | Toleransi bawaan: kembali 15 menit, boleh keluar lebih awal 30 menit | PRD menyebut bisa diatur tanpa nilai bawaan |
| A71 | Tombol Panggil Musyrif mengirim notifikasi aplikasi, bukan WhatsApp | Harus seketika dan tidak ikut jeda antrean WhatsApp |
| A72 | Referensi 114 surat dimasukkan lewat migrasi, bukan seeder | Data rujukan yang sama di semua lingkungan; validasi setoran bergantung padanya. Jumlah 6236 ayat diuji otomatis |
| A73 | Peta hafalan menghitung surat yang semua ayatnya tercakup ziyadah (predikat selain rasib), lalu dikelompokkan per juz awal surat | PRD tidak punya tabel batas ayat per juz, jadi peta ini perkiraan tingkat surat, bukan halaman mushaf |
| A74 | Progres target: bila target halaman kosong, target juz dikonversi 20 halaman per juz | PRD hanya menyebut target halaman atau juz |
| A75 | Tambahan kolom `exam_attempts.session_token` | Membedakan perangkat agar pengambilalihan sesi (PRD 8.8) bisa ditegakkan |
| A76 | Jawaban yang tertahan di HP masih diterima 10 menit setelah batas, lalu dinilai ulang dan tercatat sebagai peristiwa `reconnect` | UI/UX 4.3.1 meminta jawaban terkirim begitu online saat waktu habis offline, tetapi harus ada batas. Risiko: santri bisa memutus koneksi lalu menjawab lewat batas sampai 10 menit; ustadz melihat catatannya |
| A77 | Halaman ujian memakai halaman biasa dengan Alpine, bukan Livewire | Harus tetap berjalan tanpa server |
| A78 | Kabar ujian ke santri lewat notifikasi aplikasi saat terbit; pengingat H-1 dan saat mulai lewat WhatsApp belum ada | Santri tidak punya nomor WhatsApp di data; PRD 9 menyebut penerima santri |
| A79 | Multi pilihan dinilai himpunan sama persis, tanpa skor parsial | PRD menyebut parsial sebagai opsi; default paling ketat |
| A80 | Lingkungan dev Windows memakai `DB_PERSISTENT=true` dan `opcache.revalidate_freq=2`; bawaan dan test tetap tanpa koneksi persisten | Terukur menurunkan waktu server pindah halaman dari sekitar 310 ms ke 164 ms. Efek samping: perubahan kode PHP terlihat sampai 2 detik kemudian. Produksi dibahas di Tahap 13 |
| A81 | Teknologi wajah belum dipilih (D8). `FaceMatcher` memakai `FakeFaceMatcher`, yang hanya membandingkan pola terang-gelap gambar dan **bukan pengenal wajah**. Di produksi `available()` false, jadi absen wajah dan pendaftaran tertutup sampai driver sungguhan dipasang | Sesuai Master Prompt aturan 7 dan 11. Pagar produksi mencegah kehadiran santri tercatat oleh pencocokan tiruan |
| A89 | Jalur A belum diverifikasi: `FaceMatcher` memakai `enroll` dan `identify` agar adapter perangkat bisa menyimpan rujukan, bukan embedding, di `face_enrollments.embedding`. Bila ternyata perangkat hanya mengirim peristiwa absen tanpa skor, ambang dan zona abu-abu perlu dibahas ulang setelah dokumentasi resmi vendor dibaca | Catatan D8. Syarat "hanya embedding" juga perlu dicek terhadap penyimpanan di perangkat |
| A90 | Absen wajah memakai Jalur C (face-api di browser) atas pilihan pemilik: hanya untuk absensi, di tablet atau PC pesantren. Hadir otomatis mulai skor 0,60 (jarak 0,40), zona abu-abu sampai 0,52 (jarak 0,48 seperti sistem DW), dan dua kandidat berselisih kurang dari 0,06 selalu dikonfirmasi musyrif | Pemilik meminta tidak dipakai bila berisiko tinggi. Risikonya sedang selama hanya absensi dan bisa dikoreksi; bayar dengan wajah dan gerbang wajah tetap tidak dibangun. Akurasi pada santri sungguhan belum diuji |
| A82 | `face_enrollments.embedding` bertipe teks terenkripsi, bukan `jsonb` seperti PRD | PRD R10 meminta enkripsi; `jsonb` tidak bisa menyimpan ciphertext |
| A83 | **Absen selfie dihapus, keputusan pemilik 7 November 2026.** Hanya wajah yang didaftarkan admin lalu dikenali otomatis, plus kartu, QR, dan manual sebagai cadangan. Persetujuan wali tetap wajib untuk pendaftaran wajah | Konsekuensi: sebelum PoC D8 selesai, absen di produksi hanya manual, kartu, dan QR, karena pencocokan wajah tiruan tertutup di produksi |
| A84 | Wajah zona abu-abu tetap alpa sampai dikonfirmasi musyrif | Mengikuti PRD 8.9b |
| A85 | Foto dipindai wajah hanya disimpan untuk zona abu-abu; skor tinggi tidak menyimpan foto | Minimisasi data UU PDP |
| A86 | Tiap sesi memilih satu cara absen utama; kartu dan manual selalu tersedia sebagai cadangan di lembar absensi. Belum ada pilihan banyak mode utama per sesi | Memenuhi aturan selalu ada fallback dengan layar lebih sederhana |
| A88 | Bayar dengan wajah (POS-07) dan gerbang wajah (GTE-02) belum dibangun; Master Prompt Tahap 10 hanya mencakup absensi | Keduanya butuh teknologi D8 yang belum dipilih |
| A10 | `CLAUDE.md` dan `AGENTS.md` bawaan template Laravel diganti dengan catatan proyek; Laravel Boost tidak dipasang | Bukan bagian stack PRD |
