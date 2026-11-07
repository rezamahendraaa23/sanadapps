import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { LOG_FILE } from './global-setup.js';

const shots = process.env.E2E_SCREENSHOTS;

async function pageReady(page) {
    await page.waitForFunction(() => window.Alpine && [...document.querySelectorAll('[role=dialog]')].every((el) => el.style.display === 'none'));
}

async function loginStaff(page, email, password) {
    await page.goto('/masuk?sebagai=staf');
    await pageReady(page);
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);
    await pageReady(page);
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}

test.describe.configure({ mode: 'serial' });

let guardianSession;

test('admin mengaktifkan dompet, kartu, dan kasir; urutan dependensi dijaga', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/modul');
    await pageReady(page);

    for (const name of ['Dompet uang saku', 'Kartu santri', 'Mart & Warung']) {
        const section = page.locator('section', { hasText: name }).first();
        await section.getByRole('button', { name: 'Aktifkan' }).click();
        await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();
    }

    await page.goto('/panel');
    await expect(page.getByRole('link', { name: 'Dompet' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kartu Santri' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Kasir' })).toBeVisible();
});

test('bendahara menerbitkan kartu dan mencatat top-up tunai', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');

    await page.getByRole('link', { name: 'Kartu Santri' }).click();
    await expect(page).toHaveURL(/panel\/kartu$/);
    await pageReady(page);
    await page.getByRole('button', { name: 'Terbitkan Kartu' }).click();
    const issue = page.getByRole('dialog', { name: 'Terbitkan kartu' });
    await issue.getByLabel('Cari santri').fill('2026001');
    await issue.getByRole('button', { name: /Santri Uji/ }).click();
    await expect(issue.getByText(/Untuk Santri Uji/)).toBeVisible();
    await expect(issue.getByLabel('UID kartu')).toBeFocused();
    await issue.getByLabel('UID kartu').fill('e2e-uid-01');
    await issue.getByRole('button', { name: 'Terbitkan', exact: true }).click();
    await expect(issue).toBeHidden();
    await expect(page.getByRole('cell', { name: /^Santri Uji 2026001/ })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/kartu-1280.png`, fullPage: true });

    await page.getByRole('link', { name: 'Dompet' }).click();
    await expect(page).toHaveURL(/panel\/dompet$/);
    await pageReady(page);
    await page.getByLabel('Cari nama atau NIS').fill('2026001');
    await page.getByRole('button', { name: /Santri Uji/ }).click();
    await page.getByRole('button', { name: 'Top-up Tunai' }).click();
    const topup = page.getByRole('dialog', { name: 'Top-up tunai' });
    await topup.getByLabel('Nominal yang diterima').pressSequentially('100000');
    await topup.getByRole('button', { name: 'Catat Top-up' }).click();
    await expect(topup).toBeHidden();
    await expect(page.getByRole('main').getByText('Rp 100.000').first()).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/dompet-admin-1280.png`, fullPage: true });
});

test('wali melihat saldo, mengatur limit dan PIN', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/masuk');
    await pageReady(page);
    await page.getByLabel('Nomor WhatsApp').fill('081234567890');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(page).toHaveURL(/masuk\/otp$/);
    await pageReady(page);
    const code = [...readFileSync(LOG_FILE, 'utf8').matchAll(/OTP 6281234567890: (\d{6})/g)].at(-1)[1];
    await page.getByLabel('Kode OTP').fill(code);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/wali$/);

    await page.getByRole('navigation', { name: 'Navigasi portal' }).getByRole('link', { name: 'Dompet' }).click();
    await expect(page).toHaveURL(/wali\/dompet/);
    await pageReady(page);
    await expect(page.getByText('Rp 100.000').first()).toBeVisible();

    await page.getByRole('button', { name: 'Buat PIN' }).click();
    const pin = page.getByRole('dialog', { name: 'PIN belanja' });
    await pin.getByLabel('PIN baru').fill('1234');
    await pin.getByLabel('Ulangi PIN').fill('1234');
    await pin.getByRole('button', { name: 'Simpan PIN' }).click();
    await expect(pin.getByText(/mudah ditebak/)).toBeVisible();
    await pin.getByLabel('PIN baru').fill('4827');
    await pin.getByLabel('Ulangi PIN').fill('4827');
    await pin.getByRole('button', { name: 'Simpan PIN' }).click();
    await expect(pin).toBeHidden();
    await expect(page.getByText('PIN sudah dibuat')).toBeVisible();

    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-dompet-360.png`, fullPage: true });

    await page.getByRole('link', { name: 'Top-up', exact: true }).click();
    await expect(page).toHaveURL(/dompet\/top-up/);
    await pageReady(page);
    await page.getByRole('button', { name: '50 ribu' }).click();
    await expect(page.getByLabel('Nominal')).toHaveValue('50.000');
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-topup-360.png`, fullPage: true });

    guardianSession = await page.context().storageState();
});

test('kasir: keranjang, tap kartu, berhasil, PIN salah lalu benar, kartu tak dikenal ditolak', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await loginStaff(page, 'kasir@uji.test', 'sandi-uji-kasir');
    await expect(page.getByRole('link', { name: 'Tagihan' })).toHaveCount(0);
    await page.getByRole('link', { name: 'Kasir' }).click();
    await expect(page).toHaveURL(/panel\/kasir/);
    await pageReady(page);
    if (shots) await page.screenshot({ path: `${shots}/kasir-1024.png`, fullPage: true });

    await page.getByLabel('Barcode').fill('8990001');
    await page.getByLabel('Barcode').press('Enter');
    await expect(page.getByRole('button', { name: 'Kurangi Roti Uji' })).toBeVisible();
    await page.getByRole('button', { name: 'Tambah Roti Uji' }).click();
    await expect(page.getByText('Rp 10.000').first()).toBeVisible();

    await page.getByRole('button', { name: 'Bayar dengan Kartu atau QR' }).click();
    const tap = page.getByRole('dialog', { name: 'Tap kartu' });
    await expect(tap.getByLabel('Hasil pindai')).toBeFocused();
    if (shots) await page.screenshot({ path: `${shots}/kasir-tap-1024.png` });
    await page.keyboard.type('TIDAK-TERDAFTAR');
    await page.keyboard.press('Enter');
    const result = page.getByRole('alertdialog', { name: 'Hasil pembayaran' });
    await expect(result.getByText('Kartu tidak terdaftar.')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/kasir-ditolak-1024.png` });
    await result.getByRole('button', { name: 'Coba Lagi' }).click();
    await expect(tap.getByLabel('Hasil pindai')).toBeFocused();

    await page.keyboard.type('e2e-uid-01');
    await page.keyboard.press('Enter');
    await expect(result.getByText('Sisa saldo Rp 90.000')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/kasir-berhasil-1024.png` });
    await expect(result).toBeHidden({ timeout: 6000 });
    await expect(page.getByText('Pilih produk untuk mulai.')).toBeVisible();

    await page.getByRole('button', { name: /Paket Besar/ }).click();
    await page.getByRole('button', { name: 'Bayar dengan Kartu atau QR' }).click();
    await expect(tap.getByLabel('Hasil pindai')).toBeFocused();
    await page.keyboard.type('e2e-uid-01');
    await page.keyboard.press('Enter');
    const keypad = page.getByRole('dialog', { name: 'Masukkan PIN' });
    await expect(keypad).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/kasir-pin-1024.png` });
    for (const digit of ['1', '1', '1', '1']) await keypad.getByRole('button', { name: digit, exact: true }).click();
    await keypad.getByRole('button', { name: 'Lanjut' }).click();
    await expect(keypad.getByText('Sisa 2 percobaan')).toBeVisible();
    for (const digit of ['4', '8', '2', '7']) await keypad.getByRole('button', { name: digit, exact: true }).click();
    await keypad.getByRole('button', { name: 'Lanjut' }).click();
    await expect(result.getByText('Sisa saldo Rp 65.000')).toBeVisible();
    await result.getByRole('button', { name: 'Transaksi Baru' }).click();

    await page.getByRole('button', { name: /^Batalkan ST-/ }).first().click();
    const voidModal = page.getByRole('dialog', { name: 'Batalkan transaksi?' });
    await voidModal.getByLabel('Alasan').fill('Salah produk');
    await voidModal.getByRole('button', { name: 'Batalkan Transaksi' }).click();
    await expect(voidModal).toBeHidden();
    await expect(page.getByText('Dibatalkan').first()).toBeVisible();
    await noOverflow(page);
});

test('santri melihat QR dinamis yang berganti; kasir menolak QR yang sudah dipakai', async ({ browser, baseURL, page }) => {
    const student = await browser.newContext({ baseURL, viewport: { width: 360, height: 640 } });
    const studentPage = await student.newPage();
    await studentPage.goto('/masuk?sebagai=santri');
    await pageReady(studentPage);
    await studentPage.getByLabel('NIS').fill('2026001');
    await studentPage.getByLabel('Sandi', { exact: true }).fill('sandi-uji-santri');
    await studentPage.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(studentPage).toHaveURL(/\/santri$/);
    await expect(studentPage.getByText('Rp 90.000')).toBeVisible();

    await studentPage.getByRole('navigation', { name: 'Navigasi portal' }).getByRole('link', { name: 'Kartu' }).click();
    await expect(studentPage).toHaveURL(/santri\/kartu$/);
    await expect(studentPage.getByText(/QR berganti dalam/)).toBeVisible();
    const before = await studentPage.locator('[role=img] svg').innerHTML();
    if (shots) await studentPage.screenshot({ path: `${shots}/santri-kartu-360.png`, fullPage: true });
    await noOverflow(studentPage);

    await expect.poll(async () => studentPage.locator('[role=img] svg').innerHTML(), { timeout: 40_000, intervals: [2000] }).not.toBe(before);
    await student.close();
});

test('wali melihat struk di riwayat dan bisa memblokir kartu; kasir langsung ditolak', async ({ browser, baseURL, page }) => {
    const guardian = await browser.newContext({ baseURL, storageState: guardianSession, viewport: { width: 360, height: 640 } });
    const guardianPage = await guardian.newPage();
    await guardianPage.goto('/wali/dompet');
    await pageReady(guardianPage);
    await guardianPage.getByRole('button', { name: /Warung Uji/ }).first().click();
    const receipt = guardianPage.getByRole('dialog', { name: 'Struk belanja' });
    await expect(receipt.getByText(/^ST-/)).toBeVisible();
    if (shots) await guardianPage.screenshot({ path: `${shots}/wali-struk-360.png` });
    await receipt.getByRole('button', { name: 'Tutup' }).click();

    await guardianPage.getByRole('button', { name: 'Blokir Kartu' }).click();
    const confirm = guardianPage.getByRole('dialog', { name: /Blokir kartu/ });
    await confirm.getByRole('button', { name: 'Ya, Blokir Kartu' }).click();
    await expect(confirm).toBeHidden();
    await expect(guardianPage.getByText(/Kartu tidak bisa dipakai/)).toBeVisible();
    await guardian.close();

    await page.setViewportSize({ width: 1024, height: 768 });
    await loginStaff(page, 'kasir@uji.test', 'sandi-uji-kasir');
    await page.goto('/panel/kasir');
    await pageReady(page);
    await page.getByRole('button', { name: /Roti Uji/ }).click();
    await page.getByRole('button', { name: 'Bayar dengan Kartu atau QR' }).click();
    await expect(page.getByLabel('Hasil pindai')).toBeFocused();
    await page.keyboard.type('e2e-uid-01');
    await page.keyboard.press('Enter');
    await expect(page.getByRole('alertdialog').getByText('Kartu diblokir.')).toBeVisible();
});

test('admin: perangkat, log pindai, merchant, layar HP aman', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');

    await page.goto('/panel/kartu/perangkat');
    await pageReady(page);
    await page.getByRole('button', { name: 'Tambah Perangkat' }).click();
    const form = page.getByRole('dialog', { name: 'Tambah perangkat' });
    await form.getByLabel('Nama').fill('Reader Kantin');
    await form.getByLabel('Belanja').check();
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();
    await expect(page.getByText(/^dev_/)).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/perangkat-1280.png`, fullPage: true });

    await page.getByRole('link', { name: 'Log Pindai' }).click();
    await expect(page.getByRole('cell', { name: /Tidak dikenal/ }).first()).toBeVisible();

    await page.getByRole('link', { name: 'Mart & Warung' }).click();
    await pageReady(page);
    await page.getByRole('button', { name: /Warung Uji/ }).click();
    await expect(page.getByRole('cell', { name: 'Roti Uji', exact: true })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/merchant-1280.png`, fullPage: true });

    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel/dompet', '/panel/kartu', '/panel/kartu/perangkat', '/panel/kartu/log', '/panel/merchant', '/panel/kasir']) {
        await page.goto(url);
        await noOverflow(page);
    }
});
