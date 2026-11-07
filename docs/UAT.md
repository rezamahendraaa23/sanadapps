# Kriteria Penerimaan (UAT Pilot)

Pemetaan 16 skenario PRD Sistem §15 ke bukti yang sudah ada. "Otomatis" berarti dijalankan oleh Pest atau Playwright pada setiap uji penuh. "Lapangan" berarti masih perlu dicoba dengan perangkat, akun, atau orang sungguhan sebelum pilot.

| # | Skenario | Bukti otomatis | Yang masih perlu di lapangan |
|---|---|---|---|
| 1 | Setup awal dan impor 200 santri | Pest: "mengimpor 200 baris di bawah 30 detik", laporan baris salah. E2E tahap-1-data: alur setup sampai detail santri, impor dengan pratinjau | Admin pesantren mencoba sendiri tanpa developer dan mencatat waktunya |
| 2 | Tagihan SPP, makan, diskon yatim | Pest Finance: generate wajib pratinjau, diskon lewat tab santri, generate ulang tidak menggandakan. E2E tahap-2: tarif, pratinjau, generate | Bendahara mencocokkan nominal dengan contoh §8.2 memakai tarif asli |
| 3 | Bayar Midtrans (VA, QRIS) | Pest: webhook sah melunasi, tanda tangan salah ditolak 403, webhook ganda tidak menggandakan, sinkron status tertunda. E2E tahap-3: menunggu sampai webhook sah | **Belum pernah ke sandbox Midtrans sungguhan.** Uji VA dan QRIS di sandbox, ukur lunas di bawah 1 menit, kwitansi WA terkirim lewat Fonnte asli |
| 4 | Transfer manual | Pest dan E2E tahap-2: bukti dikirim, diverifikasi jadi lunas, penolakan memunculkan alasan dan bisa unggah ulang | - |
| 5 | Top-up dan belanja kartu | Pest Wallet: dobel kirim tidak memotong dua kali, PIN di atas ambang dan terkunci 15 menit setelah 3 kali salah, limit harian. E2E tahap-4. Uji beban: 600 belanja dan 100 kirim ulang, saldo dan ledger cocok | Reader kartu USB asli di meja kasir |
| 6 | Ujian 200 santri dari HP | E2E tahap-9: Wi-Fi putus tidak menghilangkan jawaban, muat ulang tetap ada. Pest: auto-submit tepat waktu. Uji beban: 200 santri, 1.000 autosave, tidak ada jawaban hilang, p95 8 ms waktu proses | **Beban serentak sungguhan** dengan `scripts/load/exam.js` di staging, lalu uji coba dengan HP santri sungguhan dan Wi-Fi dimatikan 3 menit |
| 7 | Izin pulang end-to-end | E2E tahap-7: pengajuan, persetujuan musyrif, QR izin, keluar, masuk. Pest: keterlambatan terdeteksi walau job berkala terlewat | Penjaga gerbang mencoba dengan kartu sungguhan |
| 8 | PPDB end-to-end | E2E tahap-11: daftar dari HP tanpa akun, bayar, seleksi, diterima, daftar ulang membuat santri dan tagihan | Pembayaran PPDB lewat Midtrans sandbox (sama dengan nomor 3) |
| 9 | Matikan modul dompet | Pest: `MODULE_DISABLED` untuk JSON dan halaman penjelasan, menu hilang, data tetap. E2E tahap-1-pengaturan | - |
| 10 | Wali A membuka data anak wali B | Pest: tagihan, pembayaran, kwitansi, rapor, bukti absensi, ujian, dokumen PPDB, sesi absensi kelas dan asrama selalu 403/404. Aksi Livewire portal wali hanya menerima anak sendiri | Uji penetrasi ringan oleh pihak ketiga sebelum rilis komersial |
| 11 | Restore cadangan ke staging | Pest Concurrency: cadangan, uji restore cocok per tabel, data yang dihapus kembali setelah restore, berkas rusak ditolak. `sanad:backup-drill` jalan tiap Ahad | Restore ke server staging sungguhan dan catat waktunya terhadap RTO 4 jam |
| 12 | Papan gerbang | E2E tahap-8: keluar, papan, tap dobel diabaikan, masuk. Pest: "papan: yang terlambat naik ke atas dan diberi tanda", papan berubah setelah pindai | Layar papan di pos jaga |
| 13 | Keluar tanpa izin | E2E tahap-7: ditolak besar dengan alasan. Pest: override hanya musyrif atau admin, wajib alasan, wali dikabari | - |
| 14 | Belanja di warung | E2E tahap-5: kategori terlarang ditolak, stok berkurang, limit warung. Pest: struk, ledger merchant, stok konsisten | - |
| 15 | Penyaluran merchant | E2E tahap-5: pencairan diajukan, disetujui, dibayar, kas tercatat. Pest: saldo merchant sama dengan jumlah ledger, tidak bisa negatif | - |
| 16 | Absensi wajah | E2E tahap-10: tanpa persetujuan wali diblok, daftar, pindai, wali mencabut dan data terhapus. Pest: zona abu-abu masuk antrean tinjauan | **Kamera dan wajah sungguhan** dengan tablet atau PC pesantren, termasuk cahaya redup |

## Di luar 16 skenario, belum dicoba di perangkat

- Aplikasi Android TV untuk layar masjid: kodenya ada di `apps/android-tv-display`, belum dibangun menjadi APK dan belum dipasang di TV.
- Fonnte dengan nomor WhatsApp pesantren.
- PWA portal dan ujian di HP Android 8 dan Safari iOS.

## Hasil uji penuh terakhir

Lihat `docs/PROGRES.md`, bagian Tahap 13.
