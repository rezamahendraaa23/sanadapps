import { expect, test } from '@playwright/test';

const shots = process.env.E2E_SCREENSHOTS;

async function pageReady(page) {
    await page.waitForFunction(() => window.Alpine && [...document.querySelectorAll('[role=dialog]')].every((el) => el.style.display === 'none'));
}

async function loginAdmin(page) {
    await page.goto('/masuk?sebagai=staf');
    await pageReady(page);
    await page.getByLabel('Email').fill('admin@uji.test');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-admin');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);
    await pageReady(page);
}

test.describe.configure({ mode: 'serial' });

test('admin mengatur masjid, memasang TV dengan kode, TV tetap jalan saat offline, darurat tayang', async ({ browser }) => {
    test.setTimeout(180_000);
    const admin = await (await browser.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
    await loginAdmin(admin);
    await admin.goto('/panel/pengaturan/modul');
    await pageReady(admin);
    for (const text of ['Hitung jadwal metode Kemenag', 'Jadwal dan pengumuman di TV']) {
        const section = admin.locator('section', { hasText: text }).first();
        const enable = section.getByRole('button', { name: 'Aktifkan' });
        if (await enable.count()) {
            await enable.click();
            await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();
        }
    }

    await admin.goto('/panel/masjid?tab=lokasi');
    await pageReady(admin);
    await admin.getByLabel('Nama masjid').fill('Masjid Uji');
    await admin.getByLabel('Kota atau kabupaten').fill('Jakarta');
    await admin.getByLabel('Lintang').fill('-6.1754');
    await admin.getByLabel('Bujur').fill('106.8272');
    await admin.getByRole('button', { name: 'Simpan dan Hitung Ulang' }).click();
    await expect(admin.getByText('Jadwal 60 hari dihitung ulang.')).toBeVisible();
    await admin.getByRole('tab', { name: 'Ringkasan' }).click();
    await expect(admin.getByText('Subuh').first()).toBeVisible();
    if (shots) await admin.screenshot({ path: `${shots}/masjid-ringkasan-1280.png`, fullPage: true });

    const tvContext = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const tv = await tvContext.newPage();
    await tv.goto('/display');
    const code = (await tv.locator('.code').innerText({ timeout: 15_000 })).trim();
    expect(code).toMatch(/^\d{6}$/);

    await admin.goto('/panel/masjid/layar');
    await pageReady(admin);
    await admin.getByLabel('Kode di layar').fill(code);
    await admin.getByLabel('Nama layar').fill('TV Masjid Utama');
    await admin.getByRole('button', { name: 'Pasangkan' }).click();
    await expect(admin.getByText('TV Masjid Utama').first()).toBeVisible();

    await expect(tv.locator('.time').first()).toBeVisible({ timeout: 20_000 });
    await expect(tv.getByText('Masjid Uji')).toBeVisible();
    if (shots) await tv.screenshot({ path: `${shots}/tv-1920.png` });

    await tv.waitForFunction(() => navigator.serviceWorker && navigator.serviceWorker.controller !== null, null, { timeout: 15_000 }).catch(() => {});
    await tvContext.setOffline(true);
    await tv.reload();
    await expect(tv.locator('.time').first()).toBeVisible({ timeout: 15_000 });
    await expect(tv.getByText(/Offline/)).toBeVisible({ timeout: 20_000 });
    await tvContext.setOffline(false);

    await admin.goto('/panel/masjid/konten?tab=darurat');
    await pageReady(admin);
    await admin.getByLabel('Pesan').fill('Hujan deras, santri berkumpul di aula');
    await admin.getByLabel('Tampil sampai').fill(new Date(Date.now() + (7 + 2) * 3600 * 1000).toISOString().slice(0, 16));
    admin.once('dialog', (dialog) => dialog.accept());
    await admin.getByRole('button', { name: 'Kirim Pesan Darurat' }).click();
    await expect(admin.getByText('Pesan darurat sedang tayang', { exact: false })).toBeVisible();

    await expect(tv.getByText('Hujan deras, santri berkumpul di aula')).toBeVisible({ timeout: 15_000 });
    if (shots) await tv.screenshot({ path: `${shots}/tv-darurat-1920.png` });
    admin.once('dialog', (dialog) => dialog.accept());
    await admin.getByRole('button', { name: 'Cabut Pesan Darurat' }).click();
    await expect(tv.getByText('Hujan deras, santri berkumpul di aula')).toBeHidden({ timeout: 15_000 });
});
