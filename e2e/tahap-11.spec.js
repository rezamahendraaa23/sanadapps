import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { LOG_FILE } from './global-setup.js';

const shots = process.env.E2E_SCREENSHOTS;
const PHONE = '6281234567894';

async function pageReady(page) {
    await page.waitForFunction(() => window.Alpine && [...document.querySelectorAll('[role=dialog]')].every((el) => el.style.display === 'none'));
}

async function loginStaff(page, email, password, home = /\/panel$/) {
    await page.goto('/masuk?sebagai=staf');
    await pageReady(page);
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(home);
    await pageReady(page);
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}

function wibLocal(hoursAhead) {
    return new Date(Date.now() + (7 + hoursAhead) * 3600 * 1000).toISOString().slice(0, 16);
}

function latestOtp(phone) {
    return [...readFileSync(LOG_FILE, 'utf8').matchAll(new RegExp(`OTP ${phone}: (\\d{6})`, 'g'))].at(-1)[1];
}

test.describe.configure({ mode: 'serial' });

let regNo;

test('admin membuka periode dengan tahap berbobot 100 persen', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/modul');
    await pageReady(page);
    const section = page.locator('section', { hasText: 'Pendaftaran online, dokumen, seleksi' }).first();
    const enable = section.getByRole('button', { name: 'Aktifkan' });
    if (await enable.count()) {
        await enable.click();
        await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();
    }

    await page.goto('/panel/ppdb/periode');
    await pageReady(page);
    await page.getByRole('button', { name: 'Tambah Periode' }).click();
    const form = page.getByRole('dialog', { name: 'Tambah periode' });
    await form.getByLabel('Nama periode').fill('Gelombang Uji');
    await form.getByLabel('Dibuka').fill(wibLocal(-1));
    await form.getByLabel('Ditutup').fill(wibLocal(48));
    await form.getByLabel('Kuota').fill('5');
    await form.getByLabel('Biaya daftar (Rp)').fill('150000');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();

    await page.getByLabel('Nama tahap').fill('Dokumen');
    await page.getByLabel('Bobot (%)').fill('40');
    await page.getByRole('button', { name: 'Tambah Tahap' }).click();
    await expect(page.getByText('total bobot 40%', { exact: false })).toBeVisible();

    page.on('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Buka Pendaftaran' }).click();
    await expect(page.getByText('Total bobot tahap seleksi harus 100%.')).toBeVisible();

    await page.getByLabel('Nama tahap').fill('Wawancara');
    await page.getByLabel('Jenis', { exact: true }).selectOption('interview');
    await page.getByLabel('Bobot (%)').fill('60');
    await page.getByRole('button', { name: 'Tambah Tahap' }).click();
    await expect(page.getByText('total bobot 100%', { exact: false })).toBeVisible();
    await page.getByRole('button', { name: 'Buka Pendaftaran' }).click();
    await expect(page.getByText('Dibuka', { exact: true })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/ppdb-periode-1280.png`, fullPage: true });
});

test('calon santri mendaftar dari HP tanpa akun sampai tagihan terbit', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/');
    await page.getByRole('link', { name: 'Daftar Santri Baru' }).click();
    await expect(page).toHaveURL(/ppdb\/daftar$/);
    await pageReady(page);
    await noOverflow(page);
    await page.getByLabel('Nomor WhatsApp wali').fill('081234567894');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await page.getByLabel('Kode dari WhatsApp').waitFor();
    await expect.poll(() => latestOtp(PHONE)).toMatch(/\d{6}/);
    await page.getByLabel('Kode dari WhatsApp').fill(latestOtp(PHONE));
    await page.getByRole('button', { name: 'Lanjutkan' }).click();

    await page.getByRole('button', { name: 'Lanjut' }).click();
    await expect(page.getByLabel('Nama lengkap')).toBeFocused();
    await page.getByLabel('Nama lengkap').fill('Calon Uji');
    await page.getByLabel('Jenis kelamin').selectOption('L');
    await page.getByLabel('Tanggal lahir').fill('2012-05-05');
    await page.getByLabel('Alamat').fill('Jl. Pesantren 1, Garut');
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/ppdb-langkah1-360.png`, fullPage: true });
    await page.getByRole('button', { name: 'Lanjut' }).click();

    await page.getByLabel('Nama wali').fill('Wali Calon');
    await page.getByRole('button', { name: 'Lanjut' }).click();

    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
    for (const label of ['Kartu Keluarga', 'Akta kelahiran', 'Pas foto']) {
        await page.getByLabel(new RegExp(`Pilih berkas ${label}`)).setInputFiles({ name: 'dokumen.png', mimeType: 'image/png', buffer: png });
        await expect(page.getByAltText(`Pratinjau ${label}`)).toBeVisible();
    }
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/ppdb-dokumen-360.png`, fullPage: true });
    await page.getByRole('button', { name: 'Lanjut' }).click();

    await page.getByRole('button', { name: 'Kirim Pendaftaran' }).click();
    await expect(page.getByText('Centang persetujuan pengolahan data dulu.')).toBeVisible();
    await page.getByLabel(/menyetujui pengolahan data/).check();
    await page.getByRole('button', { name: 'Kirim Pendaftaran' }).click();

    await expect(page).toHaveURL(/ppdb\/status$/);
    await expect(page.getByText('Menunggu pembayaran')).toBeVisible();
    await expect(page.getByText('Rp 150.000')).toBeVisible();
    regNo = (await page.locator('.font-tabular', { hasText: 'PPDB-' }).first().innerText()).match(/PPDB-\d+-\d+/)[0];
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/ppdb-status-360.png`, fullPage: true });
});

test('bendahara mencatat tunai; panitia memeriksa dokumen, menilai, menerima, mengumumkan, daftar ulang', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    page.on('dialog', (dialog) => dialog.accept());
    await page.goto('/panel/ppdb/pendaftar');
    await pageReady(page);
    await page.getByRole('link', { name: 'Calon Uji' }).click();
    await expect(page).toHaveURL(/ppdb\/pendaftar\/[0-9a-f-]{36}$/);
    await pageReady(page);

    await page.getByRole('button', { name: 'Catat Tunai' }).click();
    await expect(page.getByRole('button', { name: 'Verifikasi Dokumen' })).toBeVisible();
    await page.getByRole('button', { name: 'Verifikasi Dokumen' }).click();
    await expect(page.getByText('belum disetujui', { exact: false })).toBeVisible();

    for (const label of ['Kartu Keluarga', 'Akta kelahiran', 'Pas foto']) {
        await page.getByRole('button', { name: `Setujui ${label}` }).click();
        await expect(page.locator('div', { hasText: label }).getByText('Disetujui').first()).toBeVisible();
    }
    if (shots) await page.screenshot({ path: `${shots}/ppdb-detail-1280.png`, fullPage: true });
    await page.getByRole('button', { name: 'Verifikasi Dokumen' }).click();
    await page.getByRole('button', { name: 'Masukkan ke Seleksi' }).click();

    await page.getByLabel('Dokumen (bobot 40%)').fill('100');
    await page.getByLabel('Wawancara (bobot 60%)').fill('80');
    await page.getByRole('button', { name: 'Simpan Nilai' }).click();
    await expect(page.getByText('88', { exact: true })).toBeVisible();

    await page.goto('/panel/ppdb/seleksi');
    await pageReady(page);
    await page.getByRole('button', { name: 'Terima Sesuai Kuota' }).click();
    await expect(page.getByRole('cell', { name: /Diterima, belum diumumkan/ })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/ppdb-seleksi-1280.png`, fullPage: true });
    await page.getByRole('button', { name: /Umumkan Sekarang \(1\)/ }).click();
    await expect(page.getByText('Hasil diumumkan ke 1 pendaftar.')).toBeVisible();

    await page.goto('/panel/ppdb/pendaftar?cari=Calon');
    await pageReady(page);
    await page.getByRole('link', { name: 'Calon Uji' }).click();
    await pageReady(page);
    await page.getByRole('button', { name: 'Daftar Ulang' }).click();
    const modal = page.getByRole('dialog', { name: 'Daftar ulang' });
    await expect(modal.getByText('NIS otomatis', { exact: false })).toBeVisible();
    await modal.getByRole('button', { name: 'Proses Daftar Ulang' }).click();
    await expect(page.getByText('Sandi awal santri:')).toBeVisible();
    await page.getByRole('link', { name: 'Buka data santri' }).click();
    await expect(page.getByRole('heading', { name: 'Calon Uji', level: 1 })).toBeVisible();

    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel/ppdb/periode', '/panel/ppdb/pendaftar', '/panel/ppdb/seleksi']) {
        await page.goto(url);
        await noOverflow(page);
    }
});

test('cek status publik dengan nomor dan OTP', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/ppdb/status');
    await pageReady(page);
    await page.getByLabel('Nomor pendaftaran').fill(regNo);
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await page.getByLabel('Kode dari WhatsApp').waitFor();
    await page.getByLabel('Kode dari WhatsApp').fill(latestOtp(PHONE));
    await page.getByRole('button', { name: 'Lihat Status' }).click();
    await expect(page.getByText('Sudah daftar ulang')).toBeVisible();
    await expect(page.getByText('Daftar ulang selesai.', { exact: false })).toBeVisible();
    await noOverflow(page);
});
