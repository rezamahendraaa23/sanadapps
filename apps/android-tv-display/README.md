# Aplikasi layar SANAD untuk Android TV

Aplikasi kecil yang membuka halaman `/display` SANAD layar penuh (PRD Masjid MSJ-DEV-06). Semua logika ada di halaman web; aplikasi ini hanya menjaga layar tetap tampil.

Yang dilakukan aplikasi:

- Muncul di peluncur Android TV dan mulai sendiri saat TV hidup.
- Layar tidak tidur, tanpa suara (WebView dibisukan, video tetap tanpa audio).
- Memuat ulang sendiri bila halaman macet, gagal dimuat, atau proses peramban mati.
- Menyimpan alamat server. Tekan tombol **Menu** atau tahan **OK** 3 detik di remote untuk mengubahnya.

## Status

**Belum pernah dibangun dan belum dicoba di TV sungguhan.** Kode ditulis tanpa Android SDK di mesin pengembang. Sebelum dipakai: bangun APK, pasang di TV yang akan dipakai, lalu jalankan UAT PRD Masjid nomor 17 (matikan dan hidupkan TV, layar harus kembali sendiri dalam 60 detik).

## Membangun APK

Butuh Android Studio atau JDK 17 dan Android SDK 34.

```
cd apps/android-tv-display
gradle wrapper
./gradlew assembleRelease
```

APK ada di `app/build/outputs/apk/release/`. Tanda tangani dengan kunci milik pesantren sebelum dipasang.

## Memasang di TV

1. Aktifkan Opsi Pengembang dan Debug USB atau Debug jaringan di TV.
2. `adb connect <ip-tv>` lalu `adb install app-release.apk`.
3. Buka aplikasi SANAD Layar, isi alamat server, misalnya `https://sanad.pesantren.sch.id`.
4. Kode 6 angka muncul. Masukkan di panel SANAD, menu Layar TV.
5. Di pengaturan TV: matikan penghemat layar dan tidur otomatis, izinkan aplikasi mulai saat menyala, matikan pembaruan otomatis aplikasi.
