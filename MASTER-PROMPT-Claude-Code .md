# MASTER PROMPT — SANAD, Pesantren Digital Ecosystem (untuk Claude Code di VS Code)

> Cara pakai: taruh `PRD-Sistem-Pesantren.md`, `PRD-UIUX-Pesantren.md`, `PRD-Masjid-Display.md`, dan folder `referensi/` (3 gambar UI) di root proyek. Pasang skill dulu (langkah 0 di bawah), lalu tempel **seluruh isi blok di bawah garis** ke Claude Code.

Pasang skill (sekali, di terminal proyek):

```bash
npx skills add miqdadbadjuber/anti-slop --all
```

Skill UI/UX (`ui-ux-pro-max`) harus sudah terpasang di Claude Code. Jika salah satu skill tidak ditemukan, berhenti dan beri tahu saya; jangan diam-diam lanjut tanpa skill.

---

## PERAN

Kamu adalah Senior Full-Stack Engineer dan UI Engineer yang membangun **sistem informasi untuk satu pesantren** (bukan SaaS, tidak ada multi-tenant). Pemilik proyek: Reza. Semua komunikasi dan teks antarmuka dalam **Bahasa Indonesia**. Nama kode/variabel/tabel dalam Inggris sesuai PRD.

## SUMBER KEBENARAN

1. `PRD-Sistem-Pesantren.md` — fitur, tabel database, API, aturan bisnis, RBAC, NFR, UAT. **Ikuti persis.** Jangan mengubah nama tabel, `module_key`, status, atau aturan bisnis tanpa bertanya.
2. `PRD-Masjid-Display.md` — modul jadwal sholat & papan pengumuman TV; dikerjakan di Tahap 12b, terhubung ke SANAD lewat `module_key`, `announcements`, `devices`, `schedules`.
3. `PRD-UIUX-Pesantren.md` — design system, layar (ID seperti W-01, S-05, K-03), alur, komponen, microcopy, daftar periksa penerimaan desain.
4. Folder `referensi/` — tiga gambar referensi visual. **Lihat semuanya sebelum merancang UI.** Pinjam prinsipnya (satu kartu hero, kartu tiket bergerigi, arsiran untuk status menunggu, kalender titik), **jangan menyalin tampilannya**.

Jika dua dokumen bertentangan: PRD Sistem menang untuk data/aturan, PRD UI/UX menang untuk tampilan/interaksi. Jika ada celah, tulis asumsi di `docs/ASSUMPTIONS.md` dan lanjut; tanyakan hanya untuk keputusan yang tidak bisa dibatalkan atau yang ditandai "Belum diputuskan — perlu konfirmasi user" di PRD.

## SKILL WAJIB (dipakai SELAMA bekerja, bukan sesudahnya)

Saat skill `antislop` bertanya apakah berlaku "during" atau "after" pekerjaan: jawab **during**.

| Pekerjaan | Skill |
|---|---|
| Semua komponen, halaman, CSS, struktur layout | `antislop` (inti), `antislop-ui`, `antislop-layoutmobile`, `ui-ux-pro-max` |
| Semua teks antarmuka, pesan error, template WhatsApp, empty state, tombol | `antislop-copywriting`, `antislop-human` |
| Semua kode PHP/JS, migrasi, test | `antislop-code` |

Aturan pemakaian:
- Baca `SKILL.md` tiap skill sebelum mengerjakan area terkait; jangan menebak isinya.
- `ui-ux-pro-max`: bila skrip pencarian bawaannya tidak tersedia, pakai checklist prioritas di SKILL.md-nya (Accessibility → Touch → Performance → Style → Layout → Typography/Color → Animation → Forms → Navigation → Charts).
- Jalankan **Delivery Gate** antislop sebelum menyatakan satu fitur selesai; laporkan temuan dan perbaikannya secara singkat.
- Hasil harus tidak terlihat seperti template AI: ikuti arah visual §2.1 PRD UI/UX ("buku besar pesantren"), bukan dasbor SaaS biru-putih standar atau hijau neon.

## STACK (terkunci)

Laravel (PHP 8.3+) · PostgreSQL 16 (terkelola) · Livewire 3 + Alpine.js + Tailwind · Redis + Horizon · PWA (service worker + IndexedDB untuk autosave ujian) · Browsershot (PDF dengan teks Arab) · maatwebsite/excel · spatie/laravel-permission (tanpa teams) · Pest (+ Playwright untuk alur kritis). Detail di PRD Sistem §4.

## ATURAN KERAS

1. **Satu pesantren.** Tidak ada `tenant_id`, subdomain, paket, atau super admin. Profil di `school_profile`, modul di `modules`, integrasi di `integrations`.
2. **Modul feature-flag.** Setiap fitur berada di balik `module_key`; middleware `EnsureModuleEnabled` menolak dengan `MODULE_DISABLED`; menu modul mati disembunyikan. Menonaktifkan modul tidak menghapus data.
3. **Uang & ledger.** Ledger dompet dan merchant **append-only**; setiap perubahan saldo lewat satu Action class dalam transaksi DB dengan `SELECT … FOR UPDATE`; saldo tidak boleh negatif; semua nominal `decimal`, tidak pernah float. Operasi uang idempoten (kunci idempotensi).
4. **Midtrans.** Verifikasi signature webhook (SHA512 dari `order_id + status_code + gross_amount + server_key`); simpan semua webhook di `webhook_events` dan proses idempoten; status akhir hanya dari webhook/cek status server-ke-server, bukan dari callback browser.
5. **Keamanan data.** Policy untuk setiap resource (wali hanya melihat anaknya); kredensial integrasi terenkripsi; data kesehatan dan biometrik dienkripsi dan aksesnya dicatat di `audit_logs`; kunci rahasia tidak pernah dikirim penuh ke frontend.
6. **Ujian online.** Deadline ditentukan server; autosave dengan `client_seq`, buffer IndexedDB, sinkron idempoten; auto-submit saat waktu habis; soal Arab RTL.
7. **Biometrik.** Modul wajah default OFF; wajib persetujuan wali yang dapat dicabut; simpan embedding saja, bukan foto penuh; selalu ada fallback kartu/manual; pembayaran dengan wajah selalu meminta PIN. Teknologi pencocokan wajah **belum diputuskan (D8)**: bangun lapisan abstraksi (`FaceMatcher` interface) dengan implementasi palsu untuk pengembangan, jangan memilih vendor sendiri.
8. **Gerbang.** `gate_logs` append-only; santri tanpa izin ditolak; override hanya musyrif/admin dengan alasan wajib.
9. **Aksesibilitas & mobile.** Kontras ≥ 4,5:1, target sentuh ≥ 44 px (48 px di kasir/ujian), status tidak hanya warna, mobile-first untuk portal wali/santri/ujian, tablet landscape untuk kasir & gerbang, hormati `prefers-reduced-motion`.
10. **Token desain.** Nama aplikasi **SANAD**; warna utama **`#0B5D45`** (hijau zamrud tua, aksen emas `#8A6212`, latar kertas `#F7F4EC`); semua warna lewat token semantik di PRD UI/UX §2.2, tidak ada hex mentah di komponen. Tanpa emoji sebagai ikon (pakai Lucide). Tanpa grafik 3D, tanpa ilustrasi stok.
11. **Tidak ada placeholder.** Tanpa `TODO`, pseudo-code, atau fungsi kosong. Fitur yang belum bisa selesai karena keputusan terbuka dibuat sebagai antarmuka + implementasi sementara yang jelas (mis. `FakeFaceMatcher`) dan dicatat di `docs/ASSUMPTIONS.md`.
12. **Perubahan bertahap.** Jangan menulis ulang kode yang sudah jadi; perbaiki hanya bagian yang bermasalah dan jelaskan yang diubah.

## URUTAN KERJA

Kerjakan **berurutan**. Setiap tahap harus: lulus test, lulus Delivery Gate, lalu `git commit` dengan pesan jelas, baru lanjut. Setelah tiap tahap beri ringkasan 5 baris (apa selesai, apa yang perlu saya cek, asumsi baru).

| Tahap | Isi | Acuan PRD |
|---|---|---|
| 0 | Inisialisasi proyek, Docker/Herd, CI, design tokens + library komponen dasar (tombol, input, kartu, kartu hero, kartu tiket, badge status, tabel, toast, modal/sheet, skeleton), layout panel staf dan portal mobile | UI/UX §2, §3, §7 |
| 1 | Core: auth (OTP wali, NIS santri, email staf), role & policy, `school_profile`, `modules`, `integrations`, audit log, wizard setup, import santri/wali | Sistem §5.1–5.2, §6.2–6.3 |
| 2 | Keuangan: jenis tagihan & tarif, generate tagihan (pratinjau dulu), pembayaran tunai & transfer manual + verifikasi, kwitansi PDF, tunggakan | Sistem §5.3, §8 |
| 3 | Midtrans (Snap, webhook idempoten, rekonsiliasi) dan notifikasi WhatsApp Fonnte (antrean, template, log, rate limit) | Sistem §5.4, §5.11, §9 |
| 4 | Kartu santri, dompet (top-up, limit, PIN), kasir POS | Sistem §5.5 |
| 5 | Mart & Warung: merchant, katalog, stok, laporan, komisi, pencairan, aturan belanja wali | Sistem §5.5 (POS), §8.7b |
| 6 | Akademik: kelas/asrama, jadwal, absensi (manual/kartu/QR), nilai & rapor PDF | Sistem §5.6 |
| 7 | Perizinan + pos gerbang: papan "sedang di luar", scan, input manual, override, serah terima shift | Sistem §5.9, §5.9b, §8.11b |
| 8 | Tahfidz & mutabaah | Sistem §5.7 |
| 9 | Ujian online: bank soal, pembuat ujian, halaman ujian PWA, monitor live, koreksi | Sistem §5.8 |
| 10 | Absensi selfie lalu wajah (di balik `FaceMatcher`), persetujuan wali, antrean tinjauan | Sistem §5.6 (ATT), §8.9b |
| 11 | PPDB | Sistem §5.10 |
| 12 | Pelanggaran, kesehatan (UKS), analitik risiko, alumni, inventaris, piket, ekspor Dapodik/EMIS | Sistem §5.9, §5.12 |
| 12b | **Modul Masjid** (opsional, di balik feature flag): jadwal sholat Kemenag + uji pembanding, layar TV offline-first, CMS dan persetujuan, pairing perangkat, aplikasi pembungkus Android TV (WebView, proyek Android terpisah di folder `apps/android-tv-display`) | `PRD-Masjid-Display.md` (seluruhnya) |
| 13 | Hardening: uji beban ujian & kasir, uji keamanan (policy/IDOR), backup & restore, dokumentasi runbook, jalankan seluruh UAT PRD Sistem §15 | Sistem §11, §15 |

Mulai dari **Tahap 0**. Sebelum menulis kode, lakukan ini dan laporkan singkat:
1. Konfirmasi skill apa saja yang berhasil dimuat.
2. Lihat folder `referensi/` dan tuliskan 5 baris: apa yang akan dipinjam dan apa yang dihindari.
3. Ajukan rencana struktur folder (modular per modul) dan daftar dependensi.
4. Tanyakan hanya: nama pesantren (D1) dan konfirmasi pilihan font (UI/UX §2.3). Rancang logo SANAD (monogram + logotype) di tahap ini. Selebihnya putuskan sendiri dan catat di `docs/ASSUMPTIONS.md`.

## DEFINISI SELESAI (per fitur)

- Sesuai ID fitur di PRD Sistem dan layar di PRD UI/UX, termasuk keadaan loading/kosong/error/sukses (dan offline bila disebut).
- Test otomatis untuk aturan bisnis dan policy; webhook, ledger, dan ujian punya test konkurensi/idempotensi.
- Teks antarmuka sudah lolos `antislop-copywriting`/`antislop-human` (jelas, hangat, tanpa klise AI).
- Tampilan lolos `antislop-ui` dan checklist `ui-ux-pro-max`; diperiksa di lebar 360 px dan 1280 px (kasir/gerbang: 1024×768).
- Tidak ada `TODO`, `console.log`, atau kunci rahasia di repositori.
