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

async function pay(page, uid) {
    await page.getByRole('button', { name: 'Bayar dengan Kartu atau QR' }).click();
    await expect(page.getByLabel('Hasil pindai')).toBeFocused();
    await page.keyboard.type(uid);
    await page.keyboard.press('Enter');
}

test.describe.configure({ mode: 'serial' });

let guardianSession;

test('persiapan: modul aktif, kartu dan saldo santri kedua', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/modul');
    await pageReady(page);

    for (const name of ['Dompet uang saku', 'Kartu santri', 'Mart & Warung']) {
        const section = page.locator('section', { hasText: name }).first();
        const enable = section.getByRole('button', { name: 'Aktifkan' });

        if (await enable.count()) {
            await enable.click();
            await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();
        }
    }

    await page.context().clearCookies();
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');
    await page.goto('/panel/kartu');
    await pageReady(page);
    await page.getByRole('button', { name: 'Terbitkan Kartu' }).click();
    const issue = page.getByRole('dialog', { name: 'Terbitkan kartu' });
    await issue.getByLabel('Cari santri').fill('2026002');
    await issue.getByRole('button', { name: /Santri Dua/ }).click();
    await expect(issue.getByLabel('UID kartu')).toBeFocused();
    await issue.getByLabel('UID kartu').fill('e2e-uid-t5');
    await issue.getByRole('button', { name: 'Terbitkan', exact: true }).click();
    await expect(issue).toBeHidden();

    await page.goto('/panel/dompet');
    await pageReady(page);
    await page.getByLabel('Cari nama atau NIS').fill('2026002');
    await page.getByRole('button', { name: /Santri Dua/ }).click();
    await page.getByRole('button', { name: 'Top-up Tunai' }).click();
    const topup = page.getByRole('dialog', { name: 'Top-up tunai' });
    await topup.getByLabel('Nominal yang diterima').pressSequentially('100000');
    await topup.getByRole('button', { name: 'Catat Top-up' }).click();
    await expect(topup).toBeHidden();
});

test('pengelola: kategori, produk berstok, stok masuk, opname', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'pengelola@uji.test', 'sandi-uji-pengelola');
    await expect(page.getByRole('link', { name: 'Tagihan' })).toHaveCount(0);

    await page.getByRole('link', { name: 'Mart & Warung' }).click();
    await pageReady(page);
    await expect(page.getByRole('heading', { name: 'Warung Uji' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tambah Merchant' })).toHaveCount(0);
    if (shots) await page.screenshot({ path: `${shots}/mart-beranda-1280.png`, fullPage: true });

    await page.getByRole('link', { name: 'Katalog Warung Uji' }).click();
    await expect(page).toHaveURL(/katalog$/);
    await pageReady(page);

    await page.getByRole('button', { name: 'Kategori', exact: true }).click();
    const categories = page.getByRole('dialog', { name: 'Kategori produk' });
    await categories.getByLabel('Nama kategori baru').fill('Jajanan');
    await categories.getByLabel('Wali boleh memblokir kategori ini').check();
    await categories.getByRole('button', { name: 'Tambah Kategori' }).click();
    await expect(categories.getByText('Bisa diblokir wali')).toBeVisible();
    await categories.getByRole('button', { name: 'Tutup', exact: true }).first().click();
    await expect(categories).toBeHidden();

    await page.getByRole('button', { name: 'Tambah Produk' }).click();
    const form = page.getByRole('dialog', { name: 'Tambah produk' });
    await form.getByLabel('Nama produk').fill('Permen Uji');
    await form.getByLabel('Harga jual').pressSequentially('2000');
    await form.getByLabel('Harga modal').pressSequentially('1000');
    await form.getByLabel('Barcode', { exact: true }).fill('8990002');
    await form.getByLabel('Kategori').selectOption({ label: 'Jajanan' });
    await form.getByLabel('Hitung stok produk ini').check();
    await form.getByLabel('Stok awal').fill('5');
    await form.getByLabel('Stok minimum').fill('2');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();
    await expect(page.getByRole('cell', { name: 'Permen Uji', exact: true })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/katalog-1280.png`, fullPage: true });

    await page.getByRole('link', { name: 'Stok', exact: true }).click();
    await expect(page).toHaveURL(/stok$/);
    await pageReady(page);
    await page.getByRole('button', { name: 'Opname Permen Uji' }).click();
    const opname = page.getByRole('dialog', { name: /Opname/ });
    await opname.getByLabel('Hasil hitung fisik').fill('4');
    await opname.getByRole('button', { name: 'Simpan' }).click();
    await expect(opname).toBeHidden();
    await expect(page.getByText(/Selisih -1 dicatat/)).toBeVisible();
    await expect(page.getByRole('cell', { name: 'Penyesuaian' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/stok-1280.png`, fullPage: true });
});

test('wali memblokir kategori dan membatasi warung lewat aturan belanja', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/masuk');
    await pageReady(page);
    await page.getByLabel('Nomor WhatsApp').fill('081234567892');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(page).toHaveURL(/masuk\/otp$/);
    await pageReady(page);
    const code = [...readFileSync(LOG_FILE, 'utf8').matchAll(/OTP 6281234567892: (\d{6})/g)].at(-1)[1];
    await page.getByLabel('Kode OTP').fill(code);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/wali$/);
    guardianSession = await page.context().storageState();

    await page.goto('/wali/dompet');
    await pageReady(page);
    await page.getByRole('link', { name: /Aturan belanja/ }).click();
    await expect(page).toHaveURL(/dompet\/aturan/);
    await pageReady(page);

    await page.getByRole('group', { name: 'Jajanan' }).getByLabel('Larang kategori ini').check();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-aturan-360.png`, fullPage: true });
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Simpan Aturan' }).click();
    await expect(page.getByText('Aturan belanja tersimpan')).toBeVisible();
});

test('kasir: kategori terlarang ditolak, stok berkurang, produk lain tetap bisa', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await loginStaff(page, 'kasir@uji.test', 'sandi-uji-kasir');
    await page.goto('/panel/kasir');
    await pageReady(page);

    await page.getByRole('button', { name: /Permen Uji/ }).click();
    await pay(page, 'e2e-uid-t5');
    const result = page.getByRole('alertdialog', { name: 'Hasil pembayaran' });
    await expect(result).toContainText('Produk ini tidak bisa dibeli dengan dompet ini: Permen Uji.');
    await expect(result).not.toContainText('Jajanan');
    if (shots) await page.screenshot({ path: `${shots}/kasir-kategori-1024.png` });
    await result.getByRole('button', { name: 'Kembali ke Keranjang' }).click();
    await page.getByRole('button', { name: 'Kosongkan' }).click();

    await page.getByRole('button', { name: /Roti Uji/ }).click();
    await pay(page, 'e2e-uid-t5');
    await expect(result).toContainText('Sisa saldo');
    await result.getByRole('button', { name: 'Transaksi Baru' }).click();
    await expect(result).toBeHidden();
});

test('pengelola mengajukan pencairan; bendahara menyetujui dan membayar; kas tercatat', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'pengelola@uji.test', 'sandi-uji-pengelola');
    await page.goto('/panel/merchant');
    await page.getByRole('link', { name: 'Laporan Warung Uji' }).click();
    await expect(page).toHaveURL(/laporan/);
    await pageReady(page);
    await expect(page.getByRole('cell', { name: 'Roti Uji', exact: true })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/laporan-merchant-1280.png`, fullPage: true });

    await page.getByRole('button', { name: 'Ajukan Pencairan' }).click();
    const request = page.getByRole('dialog', { name: 'Ajukan pencairan' });
    await request.getByLabel('Nominal').pressSequentially('99999999');
    await request.getByRole('button', { name: 'Kirim Pengajuan' }).click();
    await expect(request.getByText(/Melebihi saldo/)).toBeVisible();
    await request.getByLabel('Nominal').fill('');
    await request.getByLabel('Nominal').pressSequentially('1000');
    await request.getByRole('button', { name: 'Kirim Pengajuan' }).click();
    await expect(request).toBeHidden();
    await expect(page.getByText('Menunggu persetujuan')).toBeVisible();

    await page.context().clearCookies();
    await loginStaff(page, 'bendahara@uji.test', 'sandi-uji-bendahara');
    await page.getByRole('link', { name: 'Pencairan' }).click();
    await expect(page).toHaveURL(/pencairan$/);
    await pageReady(page);
    page.on('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Setujui Warung Uji' }).click();
    await page.getByRole('button', { name: 'Tandai Dibayar Warung Uji' }).click();
    await expect(page.getByText('Tidak ada pencairan yang perlu ditindak.')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/pencairan-1280.png`, fullPage: true });

    await page.goto('/panel/kas');
    await expect(page.getByText('Penyaluran ke Warung Uji').first()).toBeVisible();

    await page.goto('/panel/merchant/laporan');
    await expect(page.getByRole('heading', { name: 'Per merchant' })).toBeVisible();
});

test('layar HP aman', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'pengelola@uji.test', 'sandi-uji-pengelola');
    await page.goto('/panel/merchant');
    await page.getByRole('link', { name: 'Katalog Warung Uji' }).click();
    await expect(page).toHaveURL(/katalog$/);
    const base = new URL(page.url()).pathname.replace(/katalog$/, '');

    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel/merchant', `${base}katalog`, `${base}katalog/impor`, `${base}stok`, `${base}laporan`, '/panel/merchant/laporan']) {
        await page.goto(url);
        await noOverflow(page);
    }
    if (shots) await page.screenshot({ path: `${shots}/laporan-360.png`, fullPage: true });
});
