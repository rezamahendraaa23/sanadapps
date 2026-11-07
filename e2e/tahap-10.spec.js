import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { LOG_FILE } from './global-setup.js';

const shots = process.env.E2E_SCREENSHOTS;

// Kamera tiruan Chrome: gambar bergerak, tanpa dialog izin.
test.use({
    launchOptions: { args: ['--use-fake-device-for-media-stream', '--use-fake-ui-for-media-stream'] },
    permissions: ['camera'],
});

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

async function openSession(page, title, mode) {
    await page.goto('/musyrif/absensi');
    await pageReady(page);
    await page.getByLabel('Asrama', { exact: true }).selectOption({ label: 'Asrama Uji' });
    await page.getByLabel('Nama sesi').fill(title);
    await page.getByLabel('Jam mulai').fill('00:00');
    await page.getByLabel('Cara absen').last().selectOption(mode);
    await page.getByRole('button', { name: 'Buka Sesi' }).click();
    await expect(page).toHaveURL(/musyrif\/absensi\/[0-9a-f-]{36}$/);
    await pageReady(page);
}

test.describe.configure({ mode: 'serial' });

test('admin: aktifkan modul wajah dan atur ambang; selfie tidak ada lagi', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/modul');
    await pageReady(page);
    const section = page.locator('section', { hasText: 'Absensi wajah' }).first();
    const enable = section.getByRole('button', { name: 'Aktifkan' });
    if (await enable.count()) {
        await enable.click();
        await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();
    }

    await page.goto('/panel/mapel?tab=aturan');
    await pageReady(page);
    await expect(page.getByText(/selfie/i)).toHaveCount(0);
    await page.getByRole('checkbox', { name: 'Wajah di pos' }).check();
    // Kamera tiruan selalu mirip: ambang dibuat lebar agar alur zona abu-abu teruji.
    await page.getByLabel('Hadir otomatis mulai').fill('0,999');
    await page.getByLabel('Ditolak di bawah').fill('0,01');
    await page.getByRole('button', { name: 'Simpan Aturan' }).click();
    await expect(page.getByText('Aturan absensi tersimpan.')).toBeVisible();
    await expect(page.getByText(/belum ada wali yang setuju/)).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/aturan-absen-1280.png`, fullPage: true });
});

test('wajah: tanpa persetujuan diblok, wali setuju, daftar, pindai di pos, wali mencabut dan data terhapus', async ({ browser }) => {
    const options = { viewport: { width: 1280, height: 800 }, permissions: ['camera'] };
    const adminContext = await browser.newContext(options);
    const admin = await adminContext.newPage();
    await loginStaff(admin, 'admin@uji.test', 'sandi-uji-admin');
    await admin.goto('/panel/wajah');
    await pageReady(admin);
    await expect(admin.getByText('pencocokan masih tiruan', { exact: false })).toBeVisible();
    await admin.getByLabel('Cari nama atau NIS').fill('2026003');
    await admin.getByRole('button', { name: /Santri Tiga/ }).click();
    await expect(admin.getByText('Belum ada persetujuan wali', { exact: false })).toBeVisible();
    await expect(admin.getByRole('button', { name: 'Ambil Foto' })).toHaveCount(0);
    if (shots) await admin.screenshot({ path: `${shots}/wajah-terblokir-1280.png`, fullPage: true });

    const guardianContext = await browser.newContext({ viewport: { width: 360, height: 640 } });
    const guardian = await guardianContext.newPage();
    await guardian.goto('/masuk');
    await pageReady(guardian);
    await guardian.getByLabel('Nomor WhatsApp').fill('081234567893');
    await guardian.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(guardian).toHaveURL(/masuk\/otp$/);
    await pageReady(guardian);
    const code = [...readFileSync(LOG_FILE, 'utf8').matchAll(/OTP 6281234567893: (\d{6})/g)].at(-1)[1];
    await guardian.getByLabel('Kode OTP').fill(code);
    await guardian.getByRole('button', { name: 'Masuk' }).click();
    await expect(guardian).toHaveURL(/\/wali$/);
    await guardian.goto('/wali/lainnya');
    await pageReady(guardian);
    await guardian.getByRole('link', { name: 'Persetujuan data wajah' }).click();
    await expect(guardian).toHaveURL(/wali\/persetujuan-wajah$/);
    await pageReady(guardian);
    await expect(guardian.getByText('bukan foto wajahnya', { exact: false })).toBeVisible();
    await noOverflow(guardian);
    await guardian.getByRole('button', { name: /^Setuju/ }).click();
    await expect(guardian.getByText('Disetujui', { exact: true }).first()).toBeVisible();
    if (shots) await guardian.screenshot({ path: `${shots}/persetujuan-wali-360.png`, fullPage: true });

    await admin.reload();
    await pageReady(admin);
    for (let shot = 0; shot < 3; shot++) {
        await expect(admin.getByRole('button', { name: 'Ambil Foto' })).toBeEnabled();
        await admin.getByRole('button', { name: 'Ambil Foto' }).click();
        await expect(admin.getByText(new RegExp(`^${shot + 1} dari 3`))).toBeVisible();
    }
    if (shots) await admin.screenshot({ path: `${shots}/wajah-daftar-1280.png`, fullPage: true });
    await admin.getByRole('button', { name: 'Simpan Wajah' }).click();
    await expect(admin.getByText('Wajah sudah terdaftar', { exact: false })).toBeVisible();

    const musyrifContext = await browser.newContext(options);
    const musyrif = await musyrifContext.newPage();
    await loginStaff(musyrif, 'musyrif@uji.test', 'sandi-uji-musyrif', /\/musyrif$/);
    await openSession(musyrif, 'Apel Wajah', 'face');
    await musyrif.getByRole('link', { name: 'Buka Kamera Wajah' }).click();
    await expect(musyrif).toHaveURL(/absensi-wajah/);
    const kioskUrl = musyrif.url();
    await expect(musyrif.getByRole('button', { name: 'Mulai Pindai' })).toBeEnabled();
    await musyrif.getByRole('button', { name: 'Mulai Pindai' }).click();
    await expect(musyrif.getByRole('status').filter({ hasText: 'Menunggu konfirmasi musyrif' })).toBeVisible({ timeout: 20_000 });
    await expect(musyrif.getByRole('status').filter({ hasText: 'Santri Tiga' })).toBeVisible();
    if (shots) await musyrif.screenshot({ path: `${shots}/wajah-pos-1280.png`, fullPage: true });

    await musyrif.goto('/musyrif/absensi/tinjauan');
    await pageReady(musyrif);
    await expect(musyrif.getByAltText(/Foto absensi Santri Tiga/)).toBeVisible();
    await musyrif.waitForFunction(() => { const image = document.querySelector('img[alt^="Foto absensi"]'); return image?.complete && image.naturalWidth > 0; });
    if (shots) await musyrif.screenshot({ path: `${shots}/tinjauan-1280.png`, fullPage: true });
    await musyrif.getByRole('button', { name: /^Tolak Santri Tiga/ }).click();
    const reject = musyrif.getByRole('dialog', { name: 'Tolak absensi' });
    await reject.getByRole('button', { name: 'Tolak Absensi' }).click();
    await expect(reject.getByText('Isi alasan penolakan.')).toBeVisible();
    await reject.getByLabel('Alasan').fill('Bukan orangnya');
    await reject.getByRole('button', { name: 'Tolak Absensi' }).click();
    await expect(reject).toBeHidden();
    await expect(musyrif.getByText('Tidak ada absensi yang menunggu tinjauan.')).toBeVisible();

    await musyrif.goto(kioskUrl);
    await expect(musyrif.getByRole('button', { name: 'Mulai Pindai' })).toBeEnabled();
    await musyrif.getByRole('button', { name: 'Mulai Pindai' }).click();
    await expect(musyrif.getByRole('status').filter({ hasText: 'Menunggu konfirmasi musyrif' })).toBeVisible({ timeout: 20_000 });
    await musyrif.goto('/musyrif/absensi/tinjauan');
    await pageReady(musyrif);
    await musyrif.getByRole('button', { name: /^Hadir Santri Tiga/ }).click();
    await expect(musyrif.getByText('Tidak ada absensi yang menunggu tinjauan.')).toBeVisible();

    guardian.once('dialog', (dialog) => dialog.accept());
    await guardian.getByRole('button', { name: /^Cabut Persetujuan/ }).click();
    await expect(guardian.getByText('Belum disetujui')).toBeVisible();

    await admin.reload();
    await pageReady(admin);
    await expect(admin.getByText('Belum ada persetujuan wali', { exact: false })).toBeVisible();
    await expect(admin.getByText('Wajah sudah terdaftar', { exact: false })).toHaveCount(0);

    await adminContext.close();
    await guardianContext.close();
    await musyrifContext.close();
});
