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
                ['key' => 'guardians', 'permission' => 'students.view-all', 'label' => 'Wali', 'icon' => 'contact', 'route' => 'staff.guardians.index', 'module' => 'core'],
                ['key' => 'cards', 'label' => 'Kartu Santri', 'icon' => 'id-card', 'route' => 'staff.cards.index', 'module' => 'card'],
                ['key' => 'leaves', 'label' => 'Perizinan', 'icon' => 'door-open', 'route' => 'staff.leaves.index', 'module' => 'leave'],
                ['key' => 'gate', 'label' => 'Pos Gerbang', 'icon' => 'shield-check', 'route' => 'staff.gate.index', 'module' => 'gate'],
                ['key' => 'discipline', 'label' => 'Pelanggaran', 'icon' => 'clipboard-list', 'route' => 'staff.discipline.index', 'module' => 'discipline'],
                ['key' => 'health', 'label' => 'Kesehatan', 'icon' => 'heart-pulse', 'route' => 'staff.health.index', 'module' => 'health'],
            ]],
            ['label' => 'Akademik', 'items' => [
                ['key' => 'classrooms', 'permission' => 'masters.manage', 'label' => 'Kelas & Asrama', 'icon' => 'school', 'route' => 'staff.classrooms.index', 'module' => 'core'],
                ['key' => 'schedules', 'label' => 'Jadwal', 'icon' => 'calendar-days', 'route' => 'staff.schedules.index', 'module' => 'academic'],
                ['key' => 'attendance', 'label' => 'Absensi', 'icon' => 'calendar-check', 'route' => 'staff.attendance.index', 'module' => 'attendance'],
                ['key' => 'grades', 'label' => 'Nilai & Rapor', 'icon' => 'graduation-cap', 'route' => 'staff.grades.index', 'module' => 'grading'],
                ['key' => 'tahfidz', 'label' => 'Tahfidz', 'icon' => 'book-open', 'route' => 'staff.tahfidz.index', 'module' => 'tahfidz'],
                ['key' => 'exams', 'label' => 'Ujian', 'icon' => 'file-pen-line', 'route' => 'staff.exams.index', 'module' => 'exam'],
            ]],
            ['label' => 'Keuangan', 'items' => [
                ['key' => 'invoices', 'label' => 'Tagihan', 'icon' => 'receipt-text', 'route' => 'staff.invoices.index', 'module' => 'finance'],
                ['key' => 'payments', 'label' => 'Pembayaran', 'icon' => 'banknote', 'route' => 'staff.payments.index', 'module' => 'finance'],
                ['key' => 'transfer-review', 'label' => 'Verifikasi Transfer', 'icon' => 'badge-check', 'route' => 'staff.transfers.index', 'module' => 'payment_manual'],
                ['key' => 'arrears', 'label' => 'Tunggakan', 'icon' => 'clock-alert', 'route' => 'staff.arrears.index', 'module' => 'finance'],
                ['key' => 'wallets', 'label' => 'Dompet', 'icon' => 'wallet', 'route' => 'staff.wallets.index', 'module' => 'wallet'],
                ['key' => 'pos', 'label' => 'Mart & Warung', 'icon' => 'store', 'route' => 'staff.pos.index', 'module' => 'pos'],
                ['key' => 'cash', 'label' => 'Laporan Kas', 'icon' => 'landmark', 'route' => 'staff.cash.index', 'module' => 'finance'],
            ]],
            ['label' => 'PPDB', 'items' => [
                ['key' => 'ppdb-periods', 'label' => 'Periode', 'icon' => 'calendar-range', 'route' => 'staff.ppdb.periods.index', 'module' => 'ppdb'],
                ['key' => 'ppdb-applicants', 'label' => 'Pendaftar', 'icon' => 'user-plus', 'route' => 'staff.ppdb.applicants.index', 'module' => 'ppdb'],
                ['key' => 'ppdb-selection', 'label' => 'Seleksi', 'icon' => 'list-checks', 'route' => 'staff.ppdb.selection.index', 'module' => 'ppdb'],
            ]],
            ['label' => 'Lainnya', 'items' => [
                ['key' => 'analytics', 'label' => 'Analitik Risiko', 'icon' => 'triangle-alert', 'route' => 'staff.analytics.index', 'module' => 'analytics'],
                ['key' => 'alumni', 'label' => 'Alumni', 'icon' => 'user-check', 'route' => 'staff.alumni.index', 'module' => 'alumni'],
                ['key' => 'inventory', 'label' => 'Inventaris', 'icon' => 'package', 'route' => 'staff.inventory.index', 'module' => 'inventory'],
                ['key' => 'duty', 'label' => 'Piket', 'icon' => 'calendar-clock', 'route' => 'staff.duty.index', 'module' => 'duty_roster'],
                ['key' => 'export', 'label' => 'Ekspor Dapodik/EMIS', 'icon' => 'file-down', 'route' => 'staff.export.index', 'module' => 'export_dapodik'],
            ]],
            ['label' => 'Pengaturan', 'items' => [
                ['key' => 'profile', 'permission' => 'settings.manage', 'label' => 'Profil & Branding', 'icon' => 'building-2', 'route' => 'staff.settings.profile', 'module' => 'core'],
                ['key' => 'modules', 'permission' => 'settings.manage', 'label' => 'Modul', 'icon' => 'toggle-right', 'route' => 'staff.settings.modules', 'module' => 'core'],
                ['key' => 'integrations', 'permission' => 'settings.manage', 'label' => 'Integrasi', 'icon' => 'plug', 'route' => 'staff.settings.integrations', 'module' => 'core'],
                ['key' => 'fee-types', 'label' => 'Jenis Tagihan', 'icon' => 'tags', 'route' => 'staff.settings.fee-types', 'module' => 'finance'],
                ['key' => 'notifications', 'label' => 'Notifikasi', 'icon' => 'message-circle', 'route' => 'staff.settings.notifications', 'module' => 'notification_wa'],
                ['key' => 'users', 'permission' => 'users.manage', 'label' => 'Pengguna & Role', 'icon' => 'user-cog', 'route' => 'staff.settings.users', 'module' => 'core'],
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
