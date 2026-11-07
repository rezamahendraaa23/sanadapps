import { expect, test } from '@playwright/test';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { LOG_FILE } from './global-setup.js';

const shots = process.env.E2E_SCREENSHOTS;
const SERVER_KEY = 'kunci-server-e2e';

async function pageReady(page) {
    await page.waitForFunction(() => window.Alpine && [...document.querySelectorAll('[role=dialog]')].every((el) => el.style.display === 'none'));
}

async function loginStaff(page, email, password) {
    await page.goto('/masuk?sebagai=staf');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);
}

async function loginGuardian(page) {
    await page.goto('/masuk');
    await pageReady(page);
    await page.getByLabel('Nomor WhatsApp').fill('081234567891');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(page).toHaveURL(/masuk\/otp$/);
    await pageReady(page);
    const code = [...readFileSync(LOG_FILE, 'utf8').matchAll(/OTP 6281234567891: (\d{6})/g)].at(-1)[1];
    await page.getByLabel('Kode OTP').fill(code);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/wali$/);
}

function notification(orderId, gross, status, statusCode = '200') {
    const data = { order_id: orderId, status_code: statusCode, gross_amount: gross, transaction_status: status, payment_type: 'bca_va', transaction_id: 'trx-e2e' };
    data.signature_key = createHash('sha512').update(orderId + statusCode + gross + SERVER_KEY).digest('hex');
    return data;
}

test.describe.configure({ mode: 'serial' });

let orderId;
let gross;
let paymentUrl;
let guardianSession;

test('bendahara membuat tagihan untuk uji bayar online', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');
    await page.goto('/panel/tagihan/manual');
    await pageReady(page);
    await page.getByLabel('Cari nama atau NIS').fill('2026001');
    await page.getByRole('button', { name: /Santri Uji/ }).click();
    await page.getByLabel('Rincian').fill('Uang gedung');
    await page.getByLabel('Nominal').pressSequentially('125000');
    await page.getByLabel('Jatuh tempo').fill('2027-01-31');
    await page.getByRole('button', { name: 'Buat Tagihan' }).click();
    await expect(page).toHaveURL(/panel\/tagihan\/[0-9a-f-]{36}$/);
});

test('wali bayar online: menunggu sampai webhook sah melunasi, webhook palsu ditolak', async ({ page, request }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginGuardian(page);
    await page.getByRole('link', { name: 'Bayar Sekarang' }).click();
    await expect(page).toHaveURL(/wali\/bayar/);
    await pageReady(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-checkout-360.png`, fullPage: true });
    await page.getByRole('button', { name: 'Bayar Online' }).click();
    await expect(page).toHaveURL(/wali\/pembayaran\/[0-9a-f-]{36}$/);
    paymentUrl = page.url();
    await expect(page.getByText(/Menunggu pembayaran sampai/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Lanjutkan Pembayaran' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/wali-menunggu-online-360.png`, fullPage: true });

    const logged = await page.content();
    expect(logged).not.toContain(SERVER_KEY);

    const uuid = paymentUrl.split('/').pop();
    const lookup = await request.get(`/_uji/order/${uuid}`);
    ({ orderId, gross } = await lookup.json());

    const forged = { ...notification(orderId, gross, 'settlement'), signature_key: 'a'.repeat(128) };
    expect((await request.post('/webhooks/midtrans', { data: forged })).status()).toBe(403);
    await page.reload();
    await expect(page.getByRole('button', { name: 'Lanjutkan Pembayaran' })).toBeVisible();

    for (let i = 0; i < 2; i += 1) {
        expect((await request.post('/webhooks/midtrans', { data: notification(orderId, gross, 'settlement') })).status()).toBe(200);
    }

    await expect(page.getByRole('link', { name: 'Unduh Kwitansi' })).toBeVisible({ timeout: 15_000 });
    guardianSession = await page.context().storageState();
    if (shots) await page.screenshot({ path: `${shots}/wali-lunas-online-360.png`, fullPage: true });
});

test('wali melihat notifikasi di menu Lainnya; pengaturan kritis terkunci', async ({ browser, baseURL }) => {
    const context = await browser.newContext({ baseURL, storageState: guardianSession, viewport: { width: 360, height: 640 } });
    const page = await context.newPage();
    await page.goto('/wali');
    await expect(page.getByRole('link', { name: /Notifikasi, \d+ belum dibaca/ })).toBeVisible();
    await page.getByRole('link', { name: 'Lainnya' }).click();
    await expect(page.getByText('Pembayaran diterima').first()).toBeVisible();
    await expect(page.getByText('Selalu dikirim agar tagihan tidak terlewat.').first()).toBeVisible();
    const box = page.getByRole('checkbox', { name: 'Pengumuman pesantren' });
    await expect(box).toBeChecked();
    await pageReady(page);
    await box.click();
    await page.reload();
    await expect(page.getByRole('checkbox', { name: 'Pengumuman pesantren' })).not.toBeChecked();
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
    if (shots) await page.screenshot({ path: `${shots}/wali-lainnya-360.png`, fullPage: true });
});

test('admin: integrasi menampilkan webhook dan tes koneksi, template punya pratinjau', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/integrasi');
    await pageReady(page);
    await expect(page.getByText(/webhooks\/midtrans/)).toBeVisible();
    await page.getByRole('button', { name: 'Tes Koneksi' }).first().click();
    await expect(page.getByText(/Tersambung ke Midtrans/)).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/integrasi-1280.png`, fullPage: true });

    await page.getByRole('link', { name: 'Notifikasi' }).click();
    await pageReady(page);
    await page.getByRole('button', { name: /Ubah.*Pembayaran berhasil/ }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog.getByText(/Rp 625.000/)).toBeVisible();
    await dialog.getByRole('button', { name: '{metode}' }).click();
    await expect(dialog.getByLabel('Isi pesan')).toHaveValue(/\{metode\}$/);
    await dialog.getByRole('button', { name: 'Kembalikan Teks Bawaan' }).click();
    await dialog.getByRole('button', { name: 'Simpan' }).click();
    await expect(dialog).toBeHidden();
    if (shots) await page.screenshot({ path: `${shots}/template-1280.png`, fullPage: true });
});

test('bendahara: pembayaran online tercatat, rekonsiliasi terbuka, layar HP aman', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');
    await page.goto('/panel/pembayaran');
    await expect(page.locator('tbody').getByText('Online').first()).toBeVisible();
    await page.getByRole('link', { name: 'Rekonsiliasi Midtrans' }).click();
    await expect(page).toHaveURL(/panel\/rekonsiliasi$/);
    await page.getByLabel('Tampilkan').selectOption('semua');
    await expect(page.getByText('bca_va')).toBeVisible();

    await page.goto('/panel/tunggakan');
    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel/rekonsiliasi', '/panel/tunggakan']) {
        await page.goto(url);
        expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
    }
});
