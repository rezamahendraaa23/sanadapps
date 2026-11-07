import { expect, test } from '@playwright/test';

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

test.describe.configure({ mode: 'serial' });

test('admin: aktifkan modul, aturan, ambang; kelola piket, inventaris, ekspor', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/modul');
    await pageReady(page);
    for (const text of ['Aturan, poin, ambang sanksi', 'Catatan kesehatan santri', 'Skor risiko dari tunggakan', 'Data alumni dan kelulusan', 'Barang, mutasi, kondisi', 'Piket santri dan ustadz', 'Ekspor file sesuai template']) {
        const section = page.locator('section', { hasText: text }).first();
        const enable = section.getByRole('button', { name: 'Aktifkan' });
        if (await enable.count()) {
            await enable.click();
            await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();
        }
    }

    await page.goto('/panel/pelanggaran');
    await pageReady(page);
    await page.getByLabel('Nama aturan').fill('Terlambat apel');
    await page.getByLabel('Poin', { exact: true }).fill('30');
    await page.getByRole('button', { name: 'Tambah', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Nonaktifkan Terlambat apel' })).toBeVisible();
    await page.getByLabel('Nama sanksi').fill('SP1');
    await page.getByLabel('Ambang poin').fill('25');
    await page.getByRole('button', { name: 'Tambah Ambang' }).click();
    await expect(page.getByText('mulai 25 poin')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/pelanggaran-1280.png`, fullPage: true });

    await page.goto('/panel/inventaris');
    await pageReady(page);
    await page.getByRole('button', { name: 'Tambah Barang' }).click();
    const item = page.getByRole('dialog', { name: 'Tambah barang' });
    await item.getByLabel('Asrama').selectOption({ label: 'Asrama Uji' });
    await item.getByLabel('Nama barang').fill('Kasur');
    await item.getByLabel('Jumlah awal').fill('10');
    await item.getByRole('button', { name: 'Simpan' }).click();
    await expect(item).toBeHidden();
    await page.getByRole('button', { name: 'Catat Mutasi Kasur' }).click();
    const move = page.getByRole('dialog', { name: 'Catat mutasi' });
    await move.getByLabel('Jumlah').fill('99');
    await move.getByRole('button', { name: 'Simpan' }).click();
    await expect(move.getByText('Jumlah tersedia hanya 10.')).toBeVisible();
    await move.getByLabel('Jumlah').fill('2');
    await move.getByRole('button', { name: 'Simpan' }).click();
    await expect(move).toBeHidden();
    await expect(page.getByRole('cell', { name: '8', exact: true })).toBeVisible();

    await page.goto('/panel/piket');
    await pageReady(page);
    await page.getByRole('button', { name: 'Tambah Piket' }).click();
    const roster = page.getByRole('dialog', { name: 'Tambah piket' });
    await roster.getByLabel('Nama piket').fill('Dapur');
    await roster.getByLabel('Jam mulai').fill('05:00');
    await roster.getByLabel('Jam selesai').fill('06:00');
    await roster.getByRole('button', { name: 'Simpan' }).click();
    await expect(roster).toBeHidden();
    await expect(page.getByText('Dapur', { exact: true })).toBeVisible();

    await page.goto('/panel/ekspor');
    await pageReady(page);
    await expect(page.getByText('belum dicocokkan', { exact: false })).toBeVisible();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Unduh Excel' }).click();
    expect((await download).suggestedFilename()).toMatch(/\.xlsx$/);
});

test('musyrif mencatat dari HP dan ambang sanksi berjalan; admin menghitung risiko', async ({ browser }) => {
    const context = await browser.newContext({ viewport: { width: 360, height: 640 } });
    const musyrif = await context.newPage();
    await loginStaff(musyrif, 'musyrif@uji.test', 'sandi-uji-musyrif', /\/musyrif$/);
    await musyrif.getByRole('link', { name: 'Pelanggaran' }).first().click();
    await expect(musyrif).toHaveURL(/musyrif\/pelanggaran$/);
    await pageReady(musyrif);
    await musyrif.getByLabel('Cari santri').fill('Tiga');
    await musyrif.getByRole('button', { name: /Santri Tiga/ }).click();
    await musyrif.getByLabel('Jenis pelanggaran').selectOption({ label: 'Terlambat apel (30 poin)' });
    await noOverflow(musyrif);
    if (shots) await musyrif.screenshot({ path: `${shots}/pelanggaran-360.png`, fullPage: true });
    await musyrif.getByRole('button', { name: 'Simpan Catatan' }).click();
    await expect(musyrif.getByText('Pelanggaran tercatat.')).toBeVisible();
    await context.close();

    const admin = await (await browser.newContext({ viewport: { width: 1280, height: 800 } })).newPage();
    await loginStaff(admin, 'admin@uji.test', 'sandi-uji-admin');
    await admin.goto('/panel/analitik');
    await pageReady(admin);
    await admin.getByRole('button', { name: 'Hitung Sekarang' }).click();
    await expect(admin.getByText(/santri dihitung/)).toBeVisible();
    await admin.getByRole('tab', { name: /Rendah/ }).click();
    await expect(admin.getByText('Poin pelanggaran 30')).toBeVisible();
    if (shots) await admin.screenshot({ path: `${shots}/risiko-1280.png`, fullPage: true });

    await admin.goto('/panel/kesehatan');
    await pageReady(admin);
    await expect(admin.getByText('hanya melihat ringkasan', { exact: false })).toBeVisible();
    await expect(admin.getByRole('button', { name: 'Simpan' })).toHaveCount(0);

    await admin.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel/pelanggaran', '/panel/kesehatan', '/panel/analitik', '/panel/alumni', '/panel/inventaris', '/panel/piket', '/panel/ekspor']) {
        await admin.goto(url);
        await noOverflow(admin);
    }
});
