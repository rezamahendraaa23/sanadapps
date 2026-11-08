import { expect, test } from '@playwright/test';
import { bukaMenu } from './menu.js';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { LOG_FILE } from './global-setup.js';

const shots = process.env.E2E_SCREENSHOTS;

async function loginStaff(page, email, password) {
    await page.goto('/masuk?sebagai=staf');
    await page.waitForFunction(() => window.Livewire && window.Alpine);
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);
}

async function loginGuardian(page) {
    await page.goto('/masuk');
    await page.waitForFunction(() => window.Livewire && window.Alpine);
    await page.getByLabel('Nomor WhatsApp').fill('081234567891');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(page).toHaveURL(/masuk\/otp$/);
    await page.waitForFunction(() => window.Livewire && window.Alpine);
    const code = [...readFileSync(LOG_FILE, 'utf8').matchAll(/OTP 6281234567891: (\d{6})/g)].at(-1)[1];
    await page.getByLabel('Kode OTP').fill(code);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/wali$/);
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}

// Livewire dan Alpine selesai menyiapkan halaman: semua dialog sudah disembunyikan oleh Alpine.
async function pageReady(page) {
    await page.waitForFunction(() => window.Alpine && [...document.querySelectorAll('[role=dialog]')].every((el) => el.style.display === 'none'));
}

test.describe.configure({ mode: 'serial' });

test('bendahara: jenis, tarif, rekening, pratinjau, generate', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');
    await expect(page.getByText('Tingkat penagihan bulan ini')).toBeVisible();

    await bukaMenu(page, 'Jenis Tagihan');
    await page.getByRole('button', { name: 'Tambah', exact: true }).click();
    const form = page.getByRole('dialog', { name: 'Tambah jenis tagihan' });
    await form.getByLabel('Kode').fill('SPP');
    await form.getByLabel('Nama').fill('SPP');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('heading', { name: 'Tarif SPP' })).toBeVisible();

    await page.getByLabel('Nominal').pressSequentially('350000');
    await expect(page.getByLabel('Nominal')).toHaveValue('350.000');
    await page.getByLabel('Berlaku mulai').fill('2026-01-01');
    await page.getByRole('button', { name: 'Tambah Tarif' }).click();
    await expect(page.getByRole('cell', { name: 'Rp 350.000' })).toBeVisible();
    await expect(page.getByLabel('Nominal')).toHaveValue('');

    await page.getByLabel('NIS santri').fill('2026001');
    await expect(page.getByText('Tarif umum')).toBeVisible();
    await noOverflow(page);

    await bukaMenu(page, 'Tagihan');
    await page.getByRole('link', { name: 'Generate Tagihan' }).click();
    await page.getByRole('button', { name: 'Pratinjau' }).click();
    await expect(page.getByRole('heading', { name: 'Pratinjau' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/generate-1280.png`, fullPage: true });
    await page.getByRole('button', { name: /Generate \d+ Tagihan/ }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Generate', exact: true }).click();
    await expect(page.getByText(/\d+ tagihan dibuat\./)).toBeVisible();
    await page.getByRole('link', { name: 'Lihat Tagihan' }).click();
    await expect(page.getByRole('link', { name: /INV-/ }).first()).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/tagihan-1280.png`, fullPage: true });
});

test('wali di HP: beranda, tagihan, transfer, salah berkas, kirim bukti', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginGuardian(page);
    await expect(page.getByRole('heading', { name: 'Total yang harus dibayar' })).toBeVisible();
    await expect(page.getByText('Rp 350.000')).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-beranda-tagihan-360.png`, fullPage: true });

    await page.getByRole('link', { name: 'Tagihan' }).click();
    await page.getByRole('checkbox').first().check();
    await expect(page.getByRole('button', { name: 'Bayar (1)' })).toBeEnabled();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-tagihan-360.png`, fullPage: true });
    await page.getByRole('button', { name: 'Bayar (1)' }).click();
    await expect(page).toHaveURL(/wali\/bayar/);
    await pageReady(page);
    await noOverflow(page);
    await page.getByRole('button', { name: 'Lanjut Transfer' }).click();
    await expect(page).toHaveURL(/wali\/pembayaran\//);
    await expect(page.getByText('7001234567')).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-transfer-360.png`, fullPage: true });

    mkdirSync(testInfo.outputDir, { recursive: true });
    const wrong = path.join(testInfo.outputDir, 'salah.txt');
    writeFileSync(wrong, 'bukan bukti');
    await page.locator('#field-proof').setInputFiles(wrong);
    await expect(page.getByRole('alert').first()).toContainText('JPG, PNG, atau PDF');

    const proof = path.join(testInfo.outputDir, 'bukti.png');
    writeFileSync(proof, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64'));
    await page.locator('#field-proof').setInputFiles(proof);
    await expect(page.getByAltText('Pratinjau bukti')).toBeVisible();
    await page.getByRole('button', { name: 'Kirim Bukti' }).click();
    await expect(page.getByText('sedang diperiksa bendahara')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/wali-menunggu-360.png`, fullPage: true });
});

test('bendahara memverifikasi dengan pintasan; kwitansi PDF terunduh', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');
    await bukaMenu(page, 'Verifikasi Transfer');
    await expect(page.getByAltText(/Bukti transfer/)).toBeVisible();
    await expect(page.getByRole('main').getByText('Rp 350.000').first()).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/verifikasi-1280.png`, fullPage: true });

    await pageReady(page);
    await page.keyboard.press('t');
    const dialog = page.getByRole('dialog', { name: 'Tolak bukti transfer?' });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();

    await page.keyboard.press('v');
    await expect(page.getByText(/terverifikasi. Tagihan lunas./)).toBeVisible();
    await expect(page.getByText('Tidak ada yang menunggu')).toBeVisible();

    await bukaMenu(page, 'Pembayaran');
    await page.getByRole('link', { name: /KW-/ }).first().click();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Unduh Kwitansi' }).click();
    const file = await download;
    expect(file.suggestedFilename()).toMatch(/^kwitansi-KW-.*\.pdf$/);
    const content = readFileSync(await file.path());
    expect(content.subarray(0, 4).toString()).toBe('%PDF');
});

test('wali melihat lunas, kwitansi terunduh, tagihan kosong', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginGuardian(page);
    await expect(page.getByText(/sudah lunas/)).toBeVisible();
    await page.getByRole('link', { name: 'Tagihan' }).click();
    await expect(page.getByText('Semua tagihan sudah lunas.')).toBeVisible();
    await page.getByRole('tab', { name: 'Riwayat' }).click();
    await page.getByRole('link', { name: /Santri Uji/ }).first().click();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: /Unduh/ }).click();
    expect((await download).suggestedFilename()).toMatch(/\.pdf$/);
    await noOverflow(page);
});

test('layar keuangan lain tanpa scroll samping di HP dan tablet', async ({ page }) => {
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');
    for (const width of [360, 1024]) {
        await page.setViewportSize({ width, height: 768 });
        for (const url of ['/panel', '/panel/tagihan', '/panel/tagihan/generate', '/panel/tagihan/manual', '/panel/pembayaran', '/panel/pembayaran/tunai', '/panel/verifikasi-transfer', '/panel/tunggakan', '/panel/tunggakan/impor', '/panel/kas', '/panel/pengaturan/jenis-tagihan']) {
            await page.goto(url);
            await noOverflow(page);
            if (shots && width === 360) await page.screenshot({ path: `${shots}/keu${url.replaceAll('/', '-')}.png` });
        }
    }
});

test('tunai: kembalian terhitung di layar, lalu kas terisi', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');
    await page.goto('/panel/tagihan/manual');
    await page.getByLabel('Cari nama atau NIS').fill('Santri Uji');
    await page.getByRole('button', { name: /Santri Uji/ }).click();
    await page.getByLabel('Rincian').fill('Seragam');
    await page.getByLabel('Nominal').pressSequentially('200000');
    await page.getByLabel('Jatuh tempo').fill('2026-12-31');
    await page.getByRole('button', { name: 'Buat Tagihan' }).click();
    await expect(page).toHaveURL(/panel\/tagihan\/[0-9a-f-]{36}$/);

    await page.goto('/panel/pembayaran/tunai');
    await page.getByLabel('Cari nama atau NIS').fill('2026001');
    await page.getByRole('button', { name: /Santri Uji/ }).click();
    await expect(page.getByLabel('Nominal dibayarkan')).toHaveValue('200.000');
    await page.getByLabel('Uang diterima').pressSequentially('250000');
    await expect(page.getByText('Rp 50.000')).toBeVisible();
    await page.getByRole('button', { name: 'Catat Pembayaran' }).click();
    await expect(page).toHaveURL(/panel\/pembayaran\/[0-9a-f-]{36}$/);

    await pageReady(page);
    await bukaMenu(page, 'Laporan Kas');
    await expect(page).toHaveURL(new RegExp('panel.kas'));
    // Total kas ikut uji lain di database yang sama, jadi yang diperiksa baris pembayaran ini.
    await expect(page.getByRole('row', { name: /Pembayaran tagihan.*Rp 200\.000/ }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Catat Kas' }).click();
    const form = page.getByRole('dialog', { name: 'Catat kas' });
    await form.getByLabel('Kategori').selectOption({ label: 'Operasional' });
    await form.getByLabel('Nominal').pressSequentially('50000');
    await form.getByLabel('Keterangan').fill('Listrik');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('cell', { name: 'Listrik' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/kas-1280.png`, fullPage: true });
});
