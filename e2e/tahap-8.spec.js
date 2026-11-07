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

async function loginStudent(page) {
    await page.goto('/masuk?sebagai=santri');
    await pageReady(page);
    await page.getByLabel('NIS').fill('2026003');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-tiga');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/santri$/);
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}

test.describe.configure({ mode: 'serial' });

test('admin: aktifkan modul, amalan, target kelas', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/modul');
    await pageReady(page);
    const section = page.locator('section', { hasText: 'Tahfidz & mutabaah' }).first();
    await section.getByRole('button', { name: 'Aktifkan' }).click();
    await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();

    await page.goto('/panel/kelas-asrama?tab=terms');
    await pageReady(page);
    const activate = page.getByRole('row', { name: /Term Uji/ }).getByRole('button', { name: /Jadikan aktif/ });
    if (await activate.count()) {
        await activate.click();
        await expect(page.getByText('Periode aktif diganti.')).toBeVisible();
    }

    await page.goto('/panel/tahfidz/kelola?tab=amalan');
    await pageReady(page);
    await page.getByLabel('Nama amalan').fill('Sholat Dhuha');
    await page.getByRole('button', { name: 'Tambah' }).click();
    await expect(page.getByRole('button', { name: 'Matikan Sholat Dhuha' })).toBeVisible();

    await page.getByRole('tab', { name: 'Target' }).click();
    await page.getByRole('combobox', { name: 'Kelas' }).selectOption({ label: 'Kelas Uji 6' });
    await page.getByLabel('Halaman').fill('10');
    await page.getByRole('button', { name: 'Simpan Target' }).click();
    await expect(page.getByText(/Target tersimpan untuk 1 santri/)).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/tahfidz-admin-1280.png`, fullPage: true });
});

test('musyrif di HP: setoran dengan ayat divalidasi', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginStaff(page, 'musyrif@uji.test', 'sandi-uji-musyrif', /\/musyrif$/);
    await page.getByRole('link', { name: 'Setoran tahfidz' }).click();
    await expect(page).toHaveURL(/musyrif\/tahfidz/);
    await pageReady(page);
    await page.getByRole('button', { name: /Santri Tiga/ }).click();
    await page.getByLabel('Dari surat').selectOption('114');
    await expect(page.getByText('Surat ini punya 6 ayat.').first()).toBeVisible();
    await expect(page.getByLabel('Sampai surat')).toHaveValue('114');
    await page.getByLabel('Sampai ayat').fill('9');
    await page.getByLabel('Halaman').fill('2');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText(/1 sampai 6/)).toBeVisible();
    await page.getByLabel('Sampai ayat').fill('6');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText('Setoran tersimpan.')).toBeVisible();
    await expect(page.getByText('An-Nas 1 sampai 6')).toBeVisible();
    expect(await page.locator('main [lang=ar]').first().evaluate((el) => getComputedStyle(el).direction)).toBe('rtl');
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/setoran-360.png`, fullPage: true });
});

test('santri: progres, peta juz, mencentang amalan', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginStudent(page);
    await page.getByRole('navigation', { name: 'Navigasi portal' }).getByRole('link', { name: 'Tahfidz' }).click();
    await expect(page).toHaveURL(/santri\/tahfidz/);
    await pageReady(page);
    await expect(page.getByText('20%')).toBeVisible();
    await expect(page.getByRole('list', { name: 'Peta hafalan per juz' })).toBeVisible();

    const amalan = page.getByRole('switch', { name: 'Sholat Dhuha' });
    await expect(amalan).toHaveAttribute('aria-checked', 'false');
    await amalan.click();
    await expect(amalan).toHaveAttribute('aria-checked', 'true');
    await expect(page.getByText('Menunggu verifikasi')).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/santri-tahfidz-360.png`, fullPage: true });
});

test('musyrif memverifikasi; santri melihat terkunci', async ({ page, browser, baseURL }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginStaff(page, 'musyrif@uji.test', 'sandi-uji-musyrif', /\/musyrif$/);
    await page.getByRole('link', { name: /amalan mutabaah menunggu verifikasi/ }).click();
    await expect(page).toHaveURL(/musyrif\/mutabaah/);
    await pageReady(page);
    await page.getByRole('checkbox', { name: 'Sholat Dhuha' }).check();
    await page.getByRole('button', { name: 'Setujui 1 Amalan' }).click();
    await expect(page.getByText('Tidak ada amalan yang perlu diverifikasi.')).toBeVisible();
    await noOverflow(page);

    const student = await browser.newContext({ baseURL, viewport: { width: 360, height: 640 } });
    const studentPage = await student.newPage();
    await loginStudent(studentPage);
    await studentPage.goto('/santri/tahfidz');
    await expect(studentPage.getByText('Diverifikasi')).toBeVisible();
    await expect(studentPage.getByRole('switch', { name: 'Sholat Dhuha' })).toBeDisabled();
    await student.close();
});

test('admin: rekap dan ringkasan PDF terunduh; layar aman di HP', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/tahfidz/kelola');
    await pageReady(page);
    await expect(page.getByRole('cell', { name: 'Santri Tiga', exact: true })).toBeVisible();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: /PDF/ }).click();
    expect((await download).suggestedFilename()).toContain('ringkasan-hafalan');

    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel/tahfidz', '/panel/tahfidz/kelola', '/panel/tahfidz/kelola?tab=target', '/panel/tahfidz/kelola?tab=amalan', '/panel/tahfidz/mutabaah']) {
        await page.goto(url);
        await noOverflow(page);
    }
});
