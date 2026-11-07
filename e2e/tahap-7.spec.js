import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { LOG_FILE } from './global-setup.js';

const shots = process.env.E2E_SCREENSHOTS;

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

async function scan(page, value) {
    await expect(page.getByLabel('Hasil pindai')).toBeFocused();
    await page.keyboard.type(value);
    await page.keyboard.press('Enter');
}

test.describe.configure({ mode: 'serial' });

let guardianSession;

test('persiapan: admin mengaktifkan modul dan menerbitkan kartu', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/modul');
    await pageReady(page);

    for (const name of ['Kartu santri', 'Pos jaga gerbang']) {
        const section = page.locator('section', { hasText: name }).first();
        const enable = section.getByRole('button', { name: 'Aktifkan' });

        if (await enable.count()) {
            await enable.click();
            await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();
        }
    }

    await page.goto('/panel/kartu');
    await pageReady(page);
    await page.getByRole('button', { name: 'Terbitkan Kartu' }).click();
    const issue = page.getByRole('dialog', { name: 'Terbitkan kartu' });
    await issue.getByLabel('Cari santri').fill('2026003');
    await issue.getByRole('button', { name: /Santri Tiga/ }).click();
    await expect(issue.getByLabel('UID kartu')).toBeFocused();
    await issue.getByLabel('UID kartu').fill('e2e-uid-t7');
    await issue.getByRole('button', { name: 'Terbitkan', exact: true }).click();
    await expect(issue).toBeHidden();
});

test('penjaga: tanpa izin ditolak besar dengan alasan', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await loginStaff(page, 'penjaga@uji.test', 'sandi-uji-penjaga', /panel\/gerbang$/);
    await expect(page.getByRole('link', { name: 'Tagihan' })).toHaveCount(0);
    await expect(page.getByText('Tidak ada santri di luar.')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/gerbang-kosong-1024.png`, fullPage: true });

    await page.getByRole('link', { name: 'Pindai', exact: true }).click();
    await expect(page).toHaveURL(/gerbang\/pindai$/);
    await pageReady(page);
    await scan(page, 'e2e-uid-t7');
    await expect(page.getByRole('heading', { name: 'DITOLAK' })).toBeVisible();
    await expect(page.getByText('Tidak ada izin.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Panggil Musyrif' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/gerbang-ditolak-1024.png`, fullPage: true });
});

test('wali mengajukan izin; waktu salah ditolak', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/masuk');
    await pageReady(page);
    await page.getByLabel('Nomor WhatsApp').fill('081234567893');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(page).toHaveURL(/masuk\/otp$/);
    await pageReady(page);
    const code = [...readFileSync(LOG_FILE, 'utf8').matchAll(/OTP 6281234567893: (\d{6})/g)].at(-1)[1];
    await page.getByLabel('Kode OTP').fill(code);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/wali$/);
    guardianSession = await page.context().storageState();

    await page.getByRole('navigation', { name: 'Navigasi portal' }).getByRole('link', { name: 'Lainnya' }).click();
    await page.getByRole('link', { name: 'Perizinan' }).click();
    await expect(page).toHaveURL(/wali\/izin/);
    await pageReady(page);
    await page.getByRole('button', { name: 'Ajukan Izin' }).click();
    const form = page.getByRole('dialog', { name: 'Ajukan izin' });
    await form.getByRole('textbox', { name: 'Keluar', exact: true }).fill(wibLocal(0));
    await form.getByLabel('Kembali').fill(wibLocal(-1));
    await form.getByLabel('Alasan').fill('Periksa gigi');
    await form.getByRole('button', { name: 'Kirim Izin' }).click();
    await expect(form.getByText(/harus setelah/)).toBeVisible();
    await form.getByLabel('Kembali').fill(wibLocal(2));
    await form.getByRole('button', { name: 'Kirim Izin' }).click();
    await expect(form).toBeHidden();
    await expect(page.getByText('Menunggu', { exact: true })).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-izin-360.png`, fullPage: true });
});

test('musyrif menyetujui dari HP', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginStaff(page, 'musyrif@uji.test', 'sandi-uji-musyrif', /\/musyrif$/);
    await page.getByRole('link', { name: /izin menunggu persetujuan/ }).click();
    await expect(page).toHaveURL(/musyrif\/izin$/);
    await pageReady(page);
    await expect(page.getByRole('heading', { name: 'Santri Tiga' })).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/musyrif-izin-360.png`, fullPage: true });
    await page.getByRole('button', { name: 'Setujui Santri Tiga' }).click();
    await expect(page.getByText('Izin disetujui.')).toBeVisible();
});

test('wali melihat QR izin yang disetujui', async ({ browser, baseURL }) => {
    const context = await browser.newContext({ baseURL, storageState: guardianSession, viewport: { width: 360, height: 640 } });
    const page = await context.newPage();
    await page.goto('/wali/izin');
    await pageReady(page);
    await page.getByRole('button', { name: /Keluar sebentar/ }).click();
    await expect(page.getByRole('img', { name: 'QR izin untuk dipindai di gerbang' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/wali-qr-izin-360.png` });
    await context.close();
});

test('penjaga: keluar, papan, tap dobel diabaikan, masuk; riwayat dan shift', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 768 });
    await loginStaff(page, 'penjaga@uji.test', 'sandi-uji-penjaga', /panel\/gerbang$/);
    await page.goto('/panel/gerbang/pindai');
    await pageReady(page);
    await scan(page, 'e2e-uid-t7');
    await expect(page.getByRole('heading', { name: 'KELUAR' })).toBeVisible();
    await expect(page.getByText('Santri Tiga')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/gerbang-keluar-1024.png`, fullPage: true });

    await page.goto('/panel/gerbang');
    await expect(page.getByRole('heading', { name: 'Sedang di luar' })).toBeVisible();
    await expect(page.getByText('1 santri', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /Santri Tiga/ })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/gerbang-papan-1024.png`, fullPage: true });

    await page.goto('/panel/gerbang/shift');
    await pageReady(page);
    await page.getByRole('button', { name: 'Terima Shift' }).click();
    await page.getByLabel('Catatan serah terima').fill('Santri Tiga periksa gigi');
    await page.getByRole('button', { name: 'Akhiri Shift' }).click();
    await expect(page.getByText(/Saat itu 1 santri di luar/)).toBeVisible();
    await expect(page.getByText('Santri Tiga periksa gigi')).toBeVisible();

    await page.goto('/panel/gerbang/pindai');
    await pageReady(page);
    await page.waitForTimeout(5100);
    await scan(page, 'e2e-uid-t7');
    await expect(page.getByRole('heading', { name: 'MASUK' })).toBeVisible();

    await page.goto('/panel/gerbang');
    await expect(page.getByText('0 santri', { exact: true })).toBeVisible();

    await page.goto('/panel/gerbang/riwayat');
    await expect(page.getByRole('cell', { name: 'Ditolak' }).first()).toBeVisible();
    await expect(page.getByRole('cell', { name: 'Keluar', exact: true }).first()).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/gerbang-riwayat-1024.png`, fullPage: true });

    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel/gerbang', '/panel/gerbang/pindai', '/panel/gerbang/izin-hari-ini', '/panel/gerbang/riwayat', '/panel/gerbang/shift']) {
        await page.goto(url);
        await noOverflow(page);
    }
});

test('musyrif: override darurat dari papan, kembali lewat pemindaian', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'musyrif@uji.test', 'sandi-uji-musyrif', /\/musyrif$/);
    await page.goto('/panel/gerbang');
    await pageReady(page);
    await page.getByLabel('Catat manual: cari santri').fill('2026003');
    await page.getByRole('button', { name: /Santri Tiga/ }).click();
    const dialog = page.getByRole('dialog', { name: /Catat: Santri Tiga/ });
    await dialog.getByText('Keluar darurat tanpa izin').click();
    await dialog.getByLabel('Alasan').fill('Ke rumah sakit');
    await dialog.getByLabel('Batas kembali').fill(wibLocal(3));
    page.once('dialog', (confirm) => confirm.accept());
    await dialog.getByRole('button', { name: 'Catat Keluar Darurat' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('1 santri', { exact: true })).toBeVisible();

    await page.goto('/panel/gerbang/riwayat');
    await expect(page.getByRole('cell', { name: 'Darurat' })).toBeVisible();
    await expect(page.getByRole('cell', { name: 'Ke rumah sakit' })).toBeVisible();
});
