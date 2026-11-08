<?php

return [

    /*
    | Nilai awal identitas pesantren. Mulai Tahap 1 semuanya diatur admin di Pengaturan
    | (school_profile), tanpa perlu mengubah kode atau file konfigurasi.
    */
    'school' => [
        'name' => env('SANAD_SCHOOL_NAME'),
        'logo_url' => env('SANAD_SCHOOL_LOGO_URL'),
        'brand_color' => env('SANAD_BRAND_COLOR', '#0B5D45'),
    ],

    // Nilai awal zona tampilan; ditimpa school_profile.timezone per request. Penyimpanan selalu UTC.
    'display_timezone' => env('SANAD_DISPLAY_TIMEZONE', 'Asia/Jakarta'),

    // fake atau faceapi. Lihat PRD Sistem, Catatan D8.
    'face_driver' => env('FACE_DRIVER', 'fake'),

    // Mematikan pengiriman WA, dipakai uji konkurensi yang tidak berkaitan dengan notifikasi.
    'notifications_off' => env('SANAD_NOTIFICATIONS_OFF', false),

    'midtrans' => [
        // Hanya untuk uji lokal tanpa kunci sandbox; diabaikan di produksi.
        'fake' => env('SANAD_FAKE_MIDTRANS', false),
    ],

    // Cadangan aplikasi (sanad:backup). disk diisi nama disk penyimpanan luar (mis. r2) agar salinan keluar server.
    'backup' => [
        'path' => env('SANAD_BACKUP_PATH'),
        'disk' => env('SANAD_BACKUP_DISK'),
        'pg_bin' => env('SANAD_PG_BIN'),
        'keep_days' => (int) env('SANAD_BACKUP_KEEP_DAYS', 14),
    ],

    'pdf' => [
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
        'no_sandbox' => env('BROWSERSHOT_NO_SANDBOX', false),
    ],

    'navigation' => [

        'staff' => [
            ['label' => 'Utama', 'items' => [
                ['key' => 'home', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'staff.dashboard', 'module' => 'core'],
                ['key' => 'announcements', 'permission' => 'announcements.manage', 'label' => 'Pengumuman', 'icon' => 'megaphone', 'route' => 'staff.announcements.index', 'module' => 'core'],
            ]],
            ['label' => 'Kesantrian', 'items' => [
                ['key' => 'students', 'permission' => ['students.view-all', 'students.view-own-class', 'students.view-own-dormitory'], 'label' => 'Santri', 'icon' => 'users', 'route' => 'staff.students.index', 'module' => 'core'],
                ['key' => 'org', 'permission' => 'masters.manage', 'label' => 'Struktur Pesantren', 'icon' => 'network', 'route' => 'staff.org.index', 'module' => 'core'],
                ['key' => 'guardians', 'permission' => 'students.view-all', 'label' => 'Wali', 'icon' => 'contact', 'route' => 'staff.guardians.index', 'module' => 'core'],
                ['key' => 'cards', 'permission' => 'cards.view', 'label' => 'Kartu Santri', 'icon' => 'id-card', 'route' => 'staff.cards.index', 'module' => 'card'],
                ['key' => 'leaves', 'permission' => 'leave.manage', 'label' => 'Perizinan', 'icon' => 'door-open', 'route' => 'staff.leaves.index', 'module' => 'leave'],
                ['key' => 'gate', 'permission' => 'gate.operate', 'label' => 'Pos Gerbang', 'icon' => 'shield-check', 'route' => 'staff.gate.index', 'module' => 'gate'],
                ['key' => 'discipline', 'permission' => ['discipline.view', 'discipline.record'], 'label' => 'Pelanggaran', 'icon' => 'clipboard-list', 'route' => 'staff.discipline.index', 'module' => 'discipline'],
                ['key' => 'health', 'permission' => ['health.manage', 'health.summary'], 'label' => 'Kesehatan', 'icon' => 'heart-pulse', 'route' => 'staff.health.index', 'module' => 'health'],
            ]],
            ['label' => 'Akademik', 'items' => [
                ['key' => 'classrooms', 'permission' => 'masters.manage', 'label' => 'Kelas & Asrama', 'icon' => 'school', 'route' => 'staff.classrooms.index', 'module' => 'core'],
                ['key' => 'subjects', 'permission' => 'academic.manage', 'label' => 'Mapel & Penugasan', 'icon' => 'book-marked', 'route' => 'staff.subjects.index', 'module' => 'academic'],
                ['key' => 'schedules', 'permission' => 'academic.manage', 'label' => 'Jadwal', 'icon' => 'calendar-days', 'route' => 'staff.schedules.index', 'module' => 'academic'],
                ['key' => 'attendance', 'permission' => 'academic.manage', 'label' => 'Absensi', 'icon' => 'calendar-check', 'route' => 'staff.attendance.index', 'module' => 'attendance'],
                ['key' => 'face', 'permission' => 'face.enroll', 'label' => 'Pendaftaran Wajah', 'icon' => 'scan-face', 'route' => 'staff.face', 'module' => 'face_attendance'],
                ['key' => 'curriculum', 'permission' => 'academic.manage', 'label' => 'Kurikulum', 'icon' => 'milestone', 'route' => 'staff.curriculum.index', 'module' => 'curriculum'],
                ['key' => 'grades', 'permission' => 'grades.manage', 'label' => 'Nilai & Rapor', 'icon' => 'graduation-cap', 'route' => 'staff.grades.index', 'module' => 'grading'],
                ['key' => 'tahfidz', 'permission' => 'tahfidz.input', 'label' => 'Tahfidz', 'icon' => 'book-open', 'route' => 'staff.tahfidz.index', 'module' => 'tahfidz'],
                ['key' => 'exams', 'permission' => 'exams.manage', 'label' => 'Ujian', 'icon' => 'file-pen-line', 'route' => 'staff.exams.index', 'module' => 'exam'],
            ]],
            ['label' => 'Keuangan', 'items' => [
                ['key' => 'invoices', 'permission' => 'finance.view', 'label' => 'Tagihan', 'icon' => 'receipt-text', 'route' => 'staff.invoices.index', 'module' => 'finance'],
                ['key' => 'payments', 'permission' => 'finance.view', 'label' => 'Pembayaran', 'icon' => 'banknote', 'route' => 'staff.payments.index', 'module' => 'finance'],
                ['key' => 'transfer-review', 'permission' => 'finance.verify', 'label' => 'Verifikasi Transfer', 'icon' => 'badge-check', 'route' => 'staff.transfers.index', 'module' => 'payment_manual'],
                ['key' => 'arrears', 'permission' => 'finance.view', 'label' => 'Tunggakan', 'icon' => 'clock-alert', 'route' => 'staff.arrears.index', 'module' => 'finance'],
                ['key' => 'wallets', 'permission' => 'wallet.view', 'label' => 'Dompet', 'icon' => 'wallet', 'route' => 'staff.wallets.index', 'module' => 'wallet'],
                ['key' => 'pos', 'permission' => 'pos.operate', 'label' => 'Kasir', 'icon' => 'store', 'route' => 'staff.pos.index', 'module' => 'pos'],
                ['key' => 'merchants', 'permission' => ['merchants.manage', 'merchants.own', 'pos.view'], 'label' => 'Mart & Warung', 'icon' => 'shopping-basket', 'route' => 'staff.merchants.index', 'module' => 'pos'],
                ['key' => 'payouts', 'permission' => 'payouts.approve', 'label' => 'Pencairan', 'icon' => 'hand-coins', 'route' => 'staff.payouts.index', 'module' => 'pos'],
                ['key' => 'cash', 'permission' => 'finance.view', 'label' => 'Laporan Kas', 'icon' => 'landmark', 'route' => 'staff.cash.index', 'module' => 'finance'],
            ]],
            ['label' => 'PPDB', 'items' => [
                ['key' => 'ppdb-periods', 'permission' => 'ppdb.view', 'label' => 'Periode', 'icon' => 'calendar-range', 'route' => 'staff.ppdb.periods.index', 'module' => 'ppdb'],
                ['key' => 'ppdb-applicants', 'permission' => 'ppdb.view', 'label' => 'Pendaftar', 'icon' => 'user-plus', 'route' => 'staff.ppdb.applicants.index', 'module' => 'ppdb'],
                ['key' => 'ppdb-selection', 'permission' => 'ppdb.view', 'label' => 'Seleksi', 'icon' => 'list-checks', 'route' => 'staff.ppdb.selection.index', 'module' => 'ppdb'],
            ]],
            ['label' => 'Masjid', 'items' => [
                ['key' => 'masjid', 'permission' => ['masjid.settings', 'masjid.schedule'], 'label' => 'Jadwal Sholat', 'icon' => 'clock', 'route' => 'staff.masjid.index', 'module' => 'masjid_prayer'],
                ['key' => 'masjid-contents', 'permission' => 'masjid.content', 'label' => 'Konten Layar', 'icon' => 'megaphone', 'route' => 'staff.masjid.contents', 'module' => 'masjid_display'],
                ['key' => 'masjid-screens', 'permission' => 'masjid.screens', 'label' => 'Layar TV', 'icon' => 'monitor', 'route' => 'staff.masjid.screens', 'module' => 'masjid_display'],
            ]],
            ['label' => 'Lainnya', 'items' => [
                ['key' => 'analytics', 'permission' => 'analytics.view', 'label' => 'Analitik Risiko', 'icon' => 'triangle-alert', 'route' => 'staff.analytics.index', 'module' => 'analytics'],
                ['key' => 'alumni', 'permission' => 'alumni.manage', 'label' => 'Alumni', 'icon' => 'user-check', 'route' => 'staff.alumni.index', 'module' => 'alumni'],
                ['key' => 'letters', 'permission' => 'letters.manage', 'label' => 'Surat', 'icon' => 'mail', 'route' => 'staff.letters.index', 'module' => 'letters'],
                ['key' => 'inventory', 'permission' => 'inventory.manage', 'label' => 'Inventaris', 'icon' => 'package', 'route' => 'staff.inventory.index', 'module' => 'inventory'],
                ['key' => 'duty', 'permission' => 'duty.manage', 'label' => 'Piket', 'icon' => 'calendar-clock', 'route' => 'staff.duty.index', 'module' => 'duty_roster'],
                ['key' => 'export', 'permission' => 'export.run', 'label' => 'Ekspor Dapodik/EMIS', 'icon' => 'file-down', 'route' => 'staff.export.index', 'module' => 'export_dapodik'],
            ]],
            ['label' => 'Pengaturan', 'items' => [
                ['key' => 'profile', 'permission' => 'settings.manage', 'label' => 'Profil & Branding', 'icon' => 'building-2', 'route' => 'staff.settings.profile', 'module' => 'core'],
                ['key' => 'modules', 'permission' => 'settings.manage', 'label' => 'Modul', 'icon' => 'toggle-right', 'route' => 'staff.settings.modules', 'module' => 'core'],
                ['key' => 'integrations', 'permission' => 'settings.manage', 'label' => 'Integrasi', 'icon' => 'plug', 'route' => 'staff.settings.integrations', 'module' => 'core'],
                ['key' => 'fee-types', 'permission' => 'finance.manage', 'label' => 'Jenis Tagihan', 'icon' => 'tags', 'route' => 'staff.settings.fee-types', 'module' => 'finance'],
                ['key' => 'notifications', 'permission' => 'settings.manage', 'label' => 'Notifikasi', 'icon' => 'message-circle', 'route' => 'staff.settings.notifications', 'module' => 'core'],
                ['key' => 'users', 'permission' => 'users.manage', 'label' => 'Pengguna & Role', 'icon' => 'user-cog', 'route' => 'staff.settings.users', 'module' => 'core'],
                ['key' => 'devices', 'permission' => 'devices.manage', 'label' => 'Perangkat', 'icon' => 'scan-line', 'route' => 'staff.cards.devices', 'module' => 'card'],
                ['key' => 'system', 'permission' => 'settings.manage', 'label' => 'Kesehatan Sistem', 'icon' => 'activity', 'route' => 'staff.settings.system', 'module' => 'core'],
                ['key' => 'audit', 'permission' => 'audit.view', 'label' => 'Audit Log', 'icon' => 'scroll-text', 'route' => 'staff.settings.audit', 'module' => 'core'],
            ]],
        ],

        'portal' => [
            'wali' => [
                ['key' => 'home', 'label' => 'Beranda', 'icon' => 'house', 'route' => 'wali.home', 'module' => 'portal_wali'],
                ['key' => 'invoices', 'label' => 'Tagihan', 'icon' => 'receipt-text', 'route' => 'wali.invoices', 'module' => 'finance'],
                ['key' => 'wallet', 'label' => 'Dompet', 'icon' => 'wallet', 'route' => 'wali.wallet', 'module' => 'wallet'],
                ['key' => 'progress', 'label' => 'Perkembangan', 'icon' => 'chart-line', 'route' => 'wali.progress', 'module' => 'portal_wali'],
                ['key' => 'more', 'label' => 'Lainnya', 'icon' => 'ellipsis', 'route' => 'wali.more', 'module' => 'portal_wali'],
            ],
            'santri' => [
                ['key' => 'home', 'label' => 'Beranda', 'icon' => 'house', 'route' => 'santri.home', 'module' => 'portal_santri'],
                ['key' => 'schedule', 'label' => 'Jadwal', 'icon' => 'calendar-days', 'route' => 'santri.schedule', 'module' => 'academic'],
                ['key' => 'exams', 'label' => 'Ujian', 'icon' => 'file-pen-line', 'route' => 'santri.exams', 'module' => 'exam'],
                ['key' => 'tahfidz', 'label' => 'Tahfidz', 'icon' => 'book-open', 'route' => 'santri.tahfidz', 'module' => 'tahfidz'],
                ['key' => 'card', 'label' => 'Kartu', 'icon' => 'id-card', 'route' => 'santri.card', 'module' => 'card'],
            ],
            'ustadz' => [
                ['key' => 'home', 'label' => 'Beranda', 'icon' => 'house', 'route' => 'ustadz.home', 'module' => 'core'],
                ['key' => 'attendance', 'label' => 'Absensi', 'icon' => 'calendar-check', 'route' => 'ustadz.attendance', 'module' => 'attendance'],
                ['key' => 'grades', 'label' => 'Nilai', 'icon' => 'graduation-cap', 'route' => 'ustadz.grades', 'module' => 'grading'],
                ['key' => 'tahfidz', 'label' => 'Tahfidz', 'icon' => 'book-open', 'route' => 'ustadz.tahfidz', 'module' => 'tahfidz'],
                ['key' => 'exams', 'label' => 'Ujian', 'icon' => 'file-pen-line', 'route' => 'ustadz.exams', 'module' => 'exam'],
            ],
            'musyrif' => [
                ['key' => 'home', 'label' => 'Beranda', 'icon' => 'house', 'route' => 'musyrif.home', 'module' => 'core'],
                ['key' => 'attendance', 'label' => 'Absensi', 'icon' => 'calendar-check', 'route' => 'musyrif.attendance', 'module' => 'attendance'],
                ['key' => 'leaves', 'label' => 'Izin', 'icon' => 'door-open', 'route' => 'musyrif.leaves', 'module' => 'leave'],
                ['key' => 'discipline', 'label' => 'Pelanggaran', 'icon' => 'clipboard-list', 'route' => 'musyrif.discipline', 'module' => 'discipline'],
                ['key' => 'more', 'label' => 'Lainnya', 'icon' => 'ellipsis', 'route' => 'musyrif.more', 'module' => 'core'],
            ],
        ],
    ],
];
