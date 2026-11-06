# Asumsi dan keputusan SANAD

Catatan keputusan saat dokumen sumber bertentangan atau punya celah. Urutan sumber: PRD Sistem menang untuk data dan aturan, PRD UI/UX menang untuk tampilan dan interaksi.

## Keputusan dari pemilik proyek

| Topik | Keputusan | Tanggal |
|---|---|---|
| Identitas pesantren (D1) | Belum ada nama tetap. Nama, logo, alamat, kontak, dan warna semuanya diatur admin di Pengaturan (dibangun di Tahap 1). Selama kosong, aplikasi tampil sebagai SANAD | 7 Nov 2026 |
| Font | Dikunci: Fraunces (judul dan angka hero), Figtree (UI dan isi), Amiri (Arab utama), Noto Naskh Arabic (Arab cadangan) | 7 Nov 2026 |
| Database | PostgreSQL 16, versi terkelola untuk produksi. Menggantikan MySQL 8 di PRD dan master prompt | 7 Nov 2026 |
| Benturan aturan antislop | Pemilik menyerahkan ke pengembang. Lucide tetap dipakai karena diminta PRD: satu set konsisten dan ringan. Em dash tidak dipakai di antarmuka | 7 Nov 2026 |

## Belum diputuskan

| Topik | Status |
|---|---|
| Lingkungan lokal | Docker Desktop belum terpasang dan WSL2 belum aktif. Sementara memakai PHP 8.3 dan PostgreSQL 16 portabel di `%LOCALAPPDATA%\sanad-tools`. Horizon butuh `pcntl`, jadi tidak bisa berjalan di Windows langsung |
| Penyedia PostgreSQL terkelola | Bagian dari D6 (hosting), belum dipilih |

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
| A10 | `CLAUDE.md` dan `AGENTS.md` bawaan template Laravel diganti dengan catatan proyek; Laravel Boost tidak dipasang | Bukan bagian stack PRD |
