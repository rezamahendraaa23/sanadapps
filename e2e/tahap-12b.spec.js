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
    await admin.getByRole('button', { name: 'Pasang Layar', exact: true }).click();
    await admin.getByLabel('Kode di layar').fill(code);
    await admin.getByLabel('Nama layar').fill('TV Masjid Utama');
    await admin.getByRole('button', { name: 'Pasangkan' }).click();
    await expect(admin.getByText('TV Masjid Utama').first()).toBeVisible();

    await expect(tv.locator('.slotime, .ring, #layar.praying').first()).toBeVisible({ timeout: 20_000 });
    await expect(tv.locator('.slotime, .ring, #layar.praying').first()).toBeAttached();
    if (shots) await tv.screenshot({ path: `${shots}/tv-1920.png` });

    await tv.waitForFunction(() => navigator.serviceWorker && navigator.serviceWorker.controller !== null, null, { timeout: 15_000 }).catch(() => {});
    await tvContext.setOffline(true);
    await tv.reload();
    await expect(tv.locator('.slotime, .ring, #layar.praying').first()).toBeVisible({ timeout: 15_000 });
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

test('perintah redup dan terang sampai ke TV; poster layar penuh lewat pemotong; cabut mengembalikan TV ke kode pasang', async ({ browser }) => {
    test.setTimeout(300_000);
    const admin = await (await browser.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
    await loginAdmin(admin);
    const tv = await (await browser.newContext({ viewport: { width: 1920, height: 1080 } })).newPage();
    await tv.goto('/display');
    const code = (await tv.locator('.code').innerText({ timeout: 15_000 })).trim();
    await admin.goto('/panel/masjid/layar');
    await pageReady(admin);
    await admin.getByRole('button', { name: 'Pasang Layar', exact: true }).click();
    await admin.getByLabel('Kode di layar').fill(code);
    await admin.getByLabel('Nama layar').fill('TV Perintah');
    // Tanpa mode sholat, supaya hasil uji tidak bergantung pada jam saat uji berjalan.
    await admin.getByLabel('Profil').selectOption('announcement_only');
    await admin.getByRole('button', { name: 'Pasangkan' }).click();
    await expect(tv.locator('.code')).toBeHidden({ timeout: 20_000 });

    await admin.getByRole('button', { name: 'Redupkan TV Perintah' }).click();
    await expect(tv.locator('.dim')).toHaveCSS('opacity', '0.6', { timeout: 20_000 });
    await expect(admin.getByText('Perintah terakhir: redupkan, sudah diterima layar.')).toBeVisible({ timeout: 20_000 });
    await tv.reload();
    await expect(tv.locator('.dim')).toHaveCSS('opacity', '0.6', { timeout: 10_000 });
    await admin.getByRole('button', { name: 'Terangkan TV Perintah' }).click();
    await expect(tv.locator('.dim')).toHaveCSS('opacity', '0', { timeout: 20_000 });

    // Poster layar penuh: gambar langsung masuk pemotong 16:9, lalu tayang tanpa langkah persetujuan.
    await admin.goto('/panel/masjid/konten');
    await pageReady(admin);
    await admin.getByRole('button', { name: 'Buat Konten' }).click();
    const form = admin.getByRole('dialog', { name: 'Buat konten' });
    await form.getByLabel('Layar penuh').check();
    await form.getByLabel('Jenis').selectOption('poster');
    await form.getByLabel('Judul').fill('Poster Maulid');
    await form.getByLabel('Gambar (JPG atau PNG)').setInputFiles('public/tv/icon-512.png');
    await expect(form.getByTestId('crop-stage')).toBeVisible();
    await expect(form.getByText('Ukuran layar 1920 x 1080', { exact: false })).toBeVisible();
    await expect(form.getByText('Gambar siap.')).toBeVisible({ timeout: 20_000 });
    if (shots) await admin.screenshot({ path: `${shots}/konten-potong-1280.png` });
    await form.getByLabel('Durasi per tampil (detik)').fill('60');
    await form.getByRole('button', { name: 'Simpan dan Tayangkan' }).click();
    await expect(admin.getByText('Konten tayang di layar', { exact: false })).toBeVisible();
    await expect(tv.locator('#layar.whole .stage img')).toBeVisible({ timeout: 90_000 });
    if (shots) await tv.screenshot({ path: `${shots}/tv-penuh-1920.png` });

    await admin.goto('/panel/masjid/layar');
    await pageReady(admin);
    admin.once('dialog', (dialog) => dialog.accept());
    await admin.getByRole('button', { name: 'Cabut TV Perintah' }).click();
    await expect(tv.locator('.code')).toBeVisible({ timeout: 20_000 });
});
