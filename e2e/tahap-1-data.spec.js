import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const shots = process.env.E2E_SCREENSHOTS;

async function login(page) {
    await page.goto('/masuk?sebagai=staf');
    await page.waitForFunction(() => window.Livewire && window.Alpine);
    await page.getByLabel('Email').fill('admin@uji.test');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-admin');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}

test.describe.configure({ mode: 'serial' });

test('alur setup: profil, tahun ajaran, kelas, asrama, tambah santri, cari, detail', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await login(page);
    await expect(page.getByRole('heading', { name: 'Setup awal pesantren' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/dashboard-1280.png`, fullPage: true });

    await page.getByRole('link', { name: 'Kelas & Asrama' }).click();
    await page.getByRole('tab', { name: 'Tahun ajaran' }).click();
    await page.getByRole('button', { name: 'Tambah Tahun Ajaran' }).click();
    const form = page.getByRole('dialog');
    await form.getByLabel('Nama').fill('2026/2027');
    await form.getByLabel('Tanggal mulai').fill('2026-07-01');
    await form.getByLabel('Tanggal selesai').fill('2026-01-01');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form.getByRole('alert')).toContainText('Tanggal selesai harus setelah');
    await form.getByLabel('Tanggal selesai').fill('2027-06-30');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();
    // Sudah ada tahun aktif yang masih berlaku, jadi yang baru tidak diaktifkan diam-diam; term-nya dibuat otomatis.
    await expect(page.getByText('Tahun ajaran dibuat. Aktifkan saat periodenya tiba.')).toBeVisible();
    await expect(page.getByRole('table', { name: 'Term 2026/2027', exact: true }).getByRole('cell', { name: 'Tahun penuh', exact: true })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/tahun-ajaran-1280.png`, fullPage: true });
    await page.getByRole('button', { name: 'Jadikan aktif 2026/2027', exact: true }).click();
    await expect(page.getByText('Periode aktif diganti.')).toBeVisible();

    await page.getByRole('tab', { name: 'Kelas' }).click();
    await page.getByRole('button', { name: 'Tambah Kelas' }).click();
    await form.getByLabel('Nama').fill('Kelas 7A');
    await form.getByLabel('Jenjang').fill('7');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('cell', { name: 'Kelas 7A', exact: true })).toBeVisible();

    await page.getByRole('tab', { name: 'Asrama' }).click();
    await page.getByRole('button', { name: 'Tambah Asrama' }).click();
    await form.getByLabel('Nama').fill('Asrama Putra');
    await form.getByLabel('Untuk').selectOption('L');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('cell', { name: 'Asrama Putra', exact: true })).toBeVisible();

    await page.getByRole('link', { name: 'Santri', exact: true }).click();
    await page.getByRole('button', { name: 'Tambah Santri' }).click();
    const addStudent = page.getByRole('dialog', { name: 'Tambah santri' });
    await expect(addStudent).toBeVisible();
    await addStudent.getByRole('button', { name: 'Batal' }).click();
    await expect(addStudent).toBeHidden();
    await page.getByRole('button', { name: 'Tambah Santri' }).click();
    await addStudent.getByRole('button', { name: 'Simpan Santri' }).click();
    await expect(addStudent.getByLabel('Nama lengkap')).toBeFocused();

    await addStudent.getByLabel('Nama lengkap').fill('Fulan Baru');
    await addStudent.getByLabel('Jenis kelamin').selectOption('L');
    await addStudent.getByLabel('Kelas', { exact: true }).selectOption({ label: 'Kelas 7A' });
    await addStudent.getByLabel('Nomor WhatsApp wali').fill('081299990001');
    await addStudent.getByLabel('Nama wali').fill('Ayah Fulan');
    await addStudent.getByRole('button', { name: 'Simpan Santri' }).click();

    await expect(page.getByRole('heading', { name: 'Fulan Baru' })).toBeVisible();
    await expect(page.getByText('Sandi awal santri:')).toBeVisible();
    await page.getByRole('tab', { name: 'Wali' }).click();
    await expect(page.getByText('Ayah Fulan')).toBeVisible();
    await page.getByRole('tab', { name: 'Riwayat kelas' }).click();
    await expect(page.getByRole('cell', { name: 'Kelas 7A', exact: true })).toBeVisible();
    await noOverflow(page);

    await page.keyboard.press('/');
    await expect(page.getByLabel(/Cari menu, santri/)).toBeFocused();
    await page.keyboard.type('fulan');
    await expect(page.getByRole('link', { name: /Fulan Baru/ }).first()).toBeVisible();
});

test('impor: template terunduh, pratinjau melaporkan baris salah, hasil tercatat', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await login(page);
    await page.goto('/panel/santri/impor');

    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Unduh Template' }).click();
    expect((await download).suggestedFilename()).toBe('template-impor-santri.xlsx');

    mkdirSync(testInfo.outputDir, { recursive: true });
    const file = path.join(testInfo.outputDir, 'santri.xlsx');
    execFileSync(process.env.PHP_BIN || 'php', ['e2e/make-import-file.php', file]);

    await page.locator('#field-file').setInputFiles(file);
    await expect(page.getByRole('button', { name: 'Periksa Berkas' })).toBeEnabled();
    await page.getByRole('button', { name: 'Periksa Berkas' }).click();
    await expect(page.getByRole('heading', { name: 'Pratinjau' })).toBeVisible();
    await expect(page.getByText('Jenis kelamin harus L atau P.')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/impor-pratinjau-1280.png`, fullPage: true });

    await page.getByRole('button', { name: /Impor 2 Santri/ }).click();
    await expect(page.getByText('2 santri berhasil diimpor, 1 baris gagal.')).toBeVisible();

    const errors = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Unduh Baris Gagal' }).click();
    expect((await errors).suggestedFilename()).toBe('baris-gagal-impor.xlsx');

    await page.getByRole('link', { name: 'Lihat Santri' }).click();
    await expect(page.getByRole('link', { name: 'Impor Satu' })).toBeVisible();
});

test('kenaikan kelas: pratinjau, konfirmasi, lalu batalkan', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await login(page);
    await page.goto('/panel/kelas-asrama');
    await page.getByRole('button', { name: 'Tambah Kelas' }).click();
    const form = page.getByRole('dialog');
    await form.getByLabel('Nama').fill('Kelas 8A');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('cell', { name: 'Kelas 8A', exact: true })).toBeVisible();

    await page.goto('/panel/santri/kenaikan-kelas');
    await page.getByLabel('Kelas asal').selectOption({ label: 'Kelas 7A' });
    await expect(page.getByText(/Pratinjau: \d+ santri/)).toBeVisible();
    await page.getByLabel('Tujuan').selectOption({ label: 'Kelas 8A' });
    await page.getByRole('button', { name: /Proses \d+ Santri/ }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Proses', exact: true }).click();
    await expect(page.getByText(/santri diproses/)).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Batalkan' }).click();
    await expect(page.getByText(/dikembalikan ke kelas semula/)).toBeVisible();
});

test('pengumuman tayang di portal santri; menu kosong tidak ada link mati', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await login(page);
    await page.getByRole('link', { name: 'Pengumuman' }).click();
    await page.getByRole('button', { name: 'Tulis Pengumuman' }).click();
    await page.getByLabel('Judul').fill('Libur semester');
    await page.getByLabel('Isi').fill('Libur mulai pekan depan.');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('heading', { name: 'Libur semester' })).toBeVisible();

    const links = await page.locator('aside').first().getByRole('link').evaluateAll((items) => items.map((item) => item.href));
    for (const link of links) {
        const response = await page.request.get(link);
        expect(response.status(), link).toBe(200);
    }

    await page.context().clearCookies();
    await page.goto('/masuk?sebagai=santri');
    await page.waitForFunction(() => window.Livewire && window.Alpine);
    await page.getByLabel('NIS').fill('2026001');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-santri');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Libur semester' })).toBeVisible();
});

test('keamanan akun, lonceng, kesehatan sistem, dan tampilan HP', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await login(page);
    await page.getByRole('button', { name: 'Notifikasi' }).click();
    await expect(page.getByText('Belum ada notifikasi.')).toBeVisible();
    await page.keyboard.press('Escape');

    await page.getByRole('button', { name: /Admin Uji/ }).click();
    await page.getByRole('link', { name: 'Keamanan Akun' }).click();
    await page.getByRole('button', { name: 'Siapkan Verifikasi Dua Langkah' }).click();
    await expect(page.locator('[data-qr] svg')).toBeVisible();
    await expect(page.getByLabel('Kode dari aplikasi')).toBeVisible();

    await page.goto('/panel/pengaturan/kesehatan-sistem');
    await expect(page.getByText('Tidak ada job gagal.')).toBeVisible();

    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel', '/panel/santri', '/panel/santri/tambah', '/panel/santri/impor', '/panel/santri/kenaikan-kelas', '/panel/santri/field-tambahan', '/panel/wali', '/panel/kelas-asrama', '/panel/pengumuman', '/panel/akun', '/panel/pengaturan/pengguna', '/panel/pengaturan/kesehatan-sistem']) {
        await page.goto(url);
        await noOverflow(page);
        if (shots) await page.screenshot({ path: `${shots}/hp${url.replaceAll('/', '-')}.png` });
    }

    await page.goto('/panel/santri');
    await expect(page.locator('#field-classroom')).toBeHidden();
    await expect(page.getByRole('link', { name: 'Kenaikan Kelas' })).toBeHidden();
    await page.getByRole('button', { name: 'Filter' }).click();
    await expect(page.locator('#field-classroom')).toBeVisible();
    await page.getByRole('button', { name: 'Aksi Lain' }).click();
    await expect(page.getByRole('link', { name: 'Kenaikan Kelas' })).toBeVisible();
    await noOverflow(page);
    await page.getByRole('button', { name: 'Menu', exact: true }).click();
    await page.getByRole('dialog', { name: 'Menu' }).getByRole('link', { name: 'Wali', exact: true }).click();
    await expect(page).toHaveURL(/panel\/wali$/);
});
