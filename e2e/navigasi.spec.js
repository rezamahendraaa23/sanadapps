import { expect, test } from '@playwright/test';

async function pageReady(page) {
    await page.waitForFunction(() => window.Alpine && [...document.querySelectorAll('[role=dialog]')].every((el) => el.style.display === 'none'));
}

async function loginAdmin(page) {
    await page.goto('/masuk?sebagai=staf');
    await page.getByLabel('Email').fill('admin@uji.test');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-admin');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);
    await pageReady(page);
}

test('pindah menu tanpa muat ulang: judul, sorotan, fokus, dan modal tetap benar', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginAdmin(page);
    await page.evaluate(() => { window.belumDimuatUlang = true; });

    const menu = page.getByRole('navigation', { name: 'Menu utama' }).first();
    await menu.getByRole('link', { name: 'Santri', exact: true }).click();
    await expect(page).toHaveURL(/panel\/santri$/);
    await expect(page).toHaveTitle(/^Santri/);
    await expect(menu.getByRole('link', { name: 'Santri', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#konten')).toBeFocused();

    await menu.getByRole('link', { name: 'Notifikasi' }).click();
    await expect(page).toHaveURL(/pengaturan\/notifikasi$/);
    await pageReady(page);
    expect(await page.evaluate(() => window.belumDimuatUlang)).toBe(true);

    await page.getByRole('button', { name: /Ubah.*Tagihan terbit/ }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Batal' }).click();
    await expect(page.getByRole('dialog')).toBeHidden();

    await page.goBack();
    await expect(page).toHaveURL(/panel\/santri$/);
});

test('kursor di atas menu tidak memuat salinan halaman yang bisa basi', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginAdmin(page);
    const requests = [];
    page.on('request', (request) => requests.push(request.url()));

    await page.getByRole('navigation', { name: 'Menu utama' }).first().getByRole('link', { name: 'Santri', exact: true }).hover();
    await page.waitForTimeout(500);

    expect(requests.filter((url) => url.includes('/panel/santri'))).toEqual([]);
});

test('sorotan tab pindah seketika walau server lambat', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginAdmin(page);
    await page.goto('/panel/pengaturan/notifikasi');
    await pageReady(page);
    await page.route('**/livewire/update', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 3000));
        await route.continue();
    });

    await page.getByRole('tab', { name: 'Log pengiriman' }).click();
    await expect(page.getByRole('tab', { name: 'Log pengiriman' })).toHaveAttribute('aria-selected', 'true', { timeout: 500 });
    await expect(page.getByRole('tab', { name: 'Template' })).toHaveAttribute('aria-selected', 'false', { timeout: 500 });
    await expect(page.getByLabel('Status')).toBeVisible({ timeout: 8000 });
    await expect(page.getByRole('tab', { name: 'Log pengiriman' })).toHaveAttribute('aria-selected', 'true');
});

test('unduhan tetap jalan, dan menu HP tidak mengunci gulir setelah pindah halaman', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginAdmin(page);
    await page.goto('/panel/santri');
    await pageReady(page);
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: /Ekspor/ }).click();
    await download;

    await page.setViewportSize({ width: 360, height: 640 });
    await page.getByRole('button', { name: 'Menu', exact: true }).click();
    await page.getByRole('dialog', { name: 'Menu' }).getByRole('link', { name: 'Wali' }).click();
    await expect(page).toHaveURL(/panel\/wali$/);
    await expect(page.getByRole('dialog', { name: 'Menu' })).toBeHidden();
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).overflow + getComputedStyle(document.body).overflow)).not.toContain('hidden');
});

test('menu samping tetap di posisinya setelah pindah halaman', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 500 });
    await page.goto('/masuk?sebagai=staf');
    await page.waitForFunction(() => window.Alpine);
    await page.getByLabel('Email').fill('admin@uji.test');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-admin');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);

    const menu = page.locator('aside [data-keep-scroll]').first();
    await menu.evaluate((element) => { element.scrollTop = element.scrollHeight; });
    const before = await menu.evaluate((element) => element.scrollTop);
    expect(before).toBeGreaterThan(0);

    const links = menu.getByRole('link');
    await links.last().click();
    await page.waitForFunction(() => document.querySelector('[data-keep-scroll]')?.scrollTop > 0);
    await expect.poll(() => menu.evaluate((element) => element.scrollTop)).toBe(before);
});

test('ruang kerja punya sidebar sendiri dan Ctrl+F membuka pencarian', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginAdmin(page);
    const shots = process.env.E2E_SCREENSHOTS;

    await page.keyboard.press('Control+f');
    await expect(page.getByLabel(/Cari menu, santri/)).toBeFocused();
    await page.keyboard.type('tunggakan');
    await expect(page.getByRole('link', { name: /Tunggakan/ }).first()).toBeVisible();
    await page.keyboard.press('Escape');
    if (shots) await page.screenshot({ path: `${shots}/sidebar-utama-1280.png`, fullPage: true });

    const main = page.getByRole('navigation', { name: 'Menu utama' }).first();
    await main.getByRole('link', { name: /^Keuangan/ }).click();
    const finance = page.getByRole('navigation', { name: 'Menu Keuangan' }).first();
    await expect(finance.getByRole('link', { name: 'Tagihan', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(finance.getByRole('link', { name: 'Santri', exact: true })).toHaveCount(0);
    if (shots) await page.screenshot({ path: `${shots}/sidebar-keuangan-1280.png` });

    await finance.getByRole('link', { name: 'Menu utama' }).click();
    await expect(page).toHaveURL(/\/panel$/);
    await expect(page.getByRole('navigation', { name: 'Menu utama' }).first().getByRole('link', { name: 'Santri', exact: true })).toBeVisible();
});
