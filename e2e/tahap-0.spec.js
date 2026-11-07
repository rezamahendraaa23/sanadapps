import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const shots = process.env.E2E_SCREENSHOTS;
if (shots) mkdirSync(shots, { recursive: true });

const pages = [
    ['beranda', '/'],
    ['galeri', '/_pratinjau'],
    ['staf', '/_pratinjau/staf'],
    ['wali', '/_pratinjau/portal/wali'],
    ['santri', '/_pratinjau/portal/santri'],
    ['ustadz', '/_pratinjau/portal/ustadz'],
    ['musyrif', '/_pratinjau/portal/musyrif'],
];

const widths = [
    [360, 640],
    [768, 1024],
    [1024, 768],
    [1280, 800],
];

function trackConsole(page) {
    const problems = [];
    page.on('console', (message) => {
        if (message.type() === 'error') problems.push(message.text());
    });
    page.on('pageerror', (error) => problems.push(error.message));
    return problems;
}

for (const [name, path] of pages) {
    for (const [width, height] of widths) {
        test(`${name} @${width}: tanpa scroll samping dan tanpa galat konsol`, async ({ page }) => {
            const problems = trackConsole(page);
            await page.setViewportSize({ width, height });
            await page.goto(path);
            await page.evaluate(() => document.fonts.ready);

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            expect(overflow).toBeLessThanOrEqual(0);
            expect(problems).toEqual([]);

            if (shots) await page.screenshot({ path: `${shots}/${name}-${width}.png`, fullPage: true });
        });
    }
}

test('target sentuh interaktif minimal 44 px di HP', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });

    for (const path of ['/_pratinjau', '/_pratinjau/staf', '/_pratinjau/portal/wali']) {
        await page.goto(path);
        const small = await page.evaluate(() =>
            [...document.querySelectorAll('main a, main button, main input, nav a, header button')]
                .filter((element) => element.offsetParent !== null && element.type !== 'hidden')
                .filter((element) => !element.closest('[class*="focus-within"]'))
                .map((element) => ({ text: element.textContent.trim().slice(0, 30), h: element.getBoundingClientRect().height }))
                .filter((item) => item.h < 44),
        );
        expect(small, path).toEqual([]);
    }
});

test('font latin dan arab benar-benar termuat', async ({ page }) => {
    await page.goto('/_pratinjau');
    await page.evaluate(() => document.fonts.ready);

    const loaded = await page.evaluate(async () => {
        await Promise.all([
            document.fonts.load('600 28px "Fredoka Variable"', 'A'),
            document.fonts.load('16px "Nunito Variable"', 'A'),
            document.fonts.load('22px "Amiri"', 'ب'),
            document.fonts.load('22px "Noto Naskh Arabic"', 'ب'),
        ]);
        return [...document.fonts].filter((font) => font.status === 'loaded').map((font) => font.family.replaceAll('"', ''));
    });

    for (const family of ['Fredoka Variable', 'Nunito Variable', 'Amiri', 'Noto Naskh Arabic']) {
        expect(loaded).toContain(family);
    }
});

test('dialog: buka, fokus terkunci, Esc menutup, fokus kembali, tombol memberi umpan balik', async ({ page }) => {
    await page.goto('/_pratinjau');
    const opener = page.getByRole('button', { name: 'Buka dialog' });
    await opener.click();

    const dialog = page.getByRole('dialog', { name: 'Blokir kartu contoh?' });
    await expect(dialog).toBeVisible();
    await expect(page.locator(':focus')).toBeVisible();
    expect(await page.evaluate(() => document.activeElement.closest('[role=dialog]') !== null)).toBe(true);

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();

    await opener.click();
    await dialog.getByRole('button', { name: 'Blokir Kartu' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('Contoh selesai. Tidak ada kartu yang diblokir.')).toBeVisible();
});

test('toast: muncul dari tombol, bisa ditutup, hilang sendiri', async ({ page }) => {
    await page.goto('/_pratinjau');
    await page.getByRole('button', { name: 'Tampilkan pesan' }).click();
    const toast = page.getByText('Pesan informasi contoh.');
    await expect(toast).toBeVisible();
    await page.getByRole('button', { name: 'Tutup pesan' }).click();
    await expect(toast).toBeHidden();

    await page.getByRole('button', { name: 'Tampilkan galat' }).click();
    const auto = page.getByText('Pesan kesalahan contoh.');
    await expect(auto).toBeVisible();
    await expect(auto).toBeHidden({ timeout: 6000 });
});

test('input rupiah memformat ribuan dan hanya menyimpan angka', async ({ page }) => {
    await page.goto('/_pratinjau');
    const field = page.getByLabel('Nominal top-up');
    await expect(field).toHaveValue('50.000');
    await field.fill('');
    await field.pressSequentially('1250000x');
    await expect(field).toHaveValue('1.250.000');
    await expect(page.locator('input[name=amount]')).toHaveValue('1250000');
});

test('tabel: urut, cari, kosong, gagal, muat ulang', async ({ page }) => {
    await page.goto('/_pratinjau');
    const firstName = page.locator('tbody tr').first().locator('td').first();
    await expect(firstName).toContainText('Santri Contoh 01');

    const nameHeader = page.getByRole('columnheader', { name: 'Santri', exact: false }).first();
    await expect(nameHeader).toHaveAttribute('aria-sort', 'ascending');
    await nameHeader.getByRole('button').click();
    await expect(firstName).toContainText('Santri Contoh 06');
    await expect(nameHeader).toHaveAttribute('aria-sort', 'descending');

    await page.getByLabel('Cari santri contoh').fill('tidak ada');
    await expect(page.getByText('Tidak ada santri contoh yang cocok')).toBeVisible();
    await page.getByRole('button', { name: 'Hapus pencarian' }).click();
    await expect(page.locator('tbody tr')).toHaveCount(6);

    await page.getByRole('button', { name: 'Coba keadaan gagal' }).click();
    await expect(page.getByText('Data contoh gagal dimuat')).toBeVisible();
    await page.getByRole('button', { name: 'Muat ulang' }).click();
    await expect(page.locator('tbody tr')).toHaveCount(6);
    await expect(page.getByText('Data contoh berhasil dimuat ulang.')).toBeVisible();
});

test('sidebar desktop: lipat, ingat pilihan, item aktif ditandai', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/_pratinjau/staf');
    const dashboard = page.locator('aside').first().getByRole('link', { name: 'Dashboard' });
    await expect(dashboard).toHaveAttribute('aria-current', 'page');

    const sidebar = page.locator('aside').first();
    expect((await sidebar.boundingBox()).width).toBe(264);
    await page.getByRole('button', { name: 'Lipat menu samping' }).click();
    await expect.poll(async () => (await sidebar.boundingBox()).width).toBe(72);

    await page.reload();
    await expect.poll(async () => (await sidebar.boundingBox()).width).toBe(72);
    await page.getByRole('button', { name: 'Lebarkan menu samping' }).click();
    await expect.poll(async () => (await sidebar.boundingBox()).width).toBe(264);
});

test('drawer HP: buka dari tombol Menu, Esc menutup, tautan menutup', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/_pratinjau/staf');
    await page.getByRole('button', { name: 'Menu' }).click();
    const drawer = page.getByRole('dialog', { name: 'Menu' });
    await expect(drawer).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();

    await page.getByRole('button', { name: 'Menu' }).click();
    await drawer.getByRole('button', { name: 'Tutup menu' }).click();
    await expect(drawer).toBeHidden();
});

test('bottom nav: lima item, konten terakhir tidak tertutup', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/_pratinjau/portal/wali');
    const nav = page.getByRole('navigation', { name: 'Navigasi portal' });
    await expect(nav.getByRole('listitem')).toHaveCount(5);
    await expect(nav.getByRole('link', { name: 'Beranda' })).toHaveAttribute('aria-current', 'page');

    const clipped = await nav.locator('li span:not(.sr-only)').evaluateAll((labels) => labels.filter((label) => label.scrollWidth > label.clientWidth + 1).length);
    expect(clipped).toBe(0);
    const widest = await nav.getByText('Perkembangan').evaluate((label) => label.getBoundingClientRect().right);
    expect(widest).toBeLessThanOrEqual(360);

    const last = page.getByRole('link', { name: 'Komponen' });
    await last.scrollIntoViewIfNeeded();
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    const lastBox = await last.boundingBox();
    const navBox = await nav.boundingBox();
    expect(lastBox.y + lastBox.height).toBeLessThanOrEqual(navBox.y);
});

test('angka hero tidak meluber dari kartunya', async ({ page }) => {
    for (const width of [360, 1024, 1280]) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto('/_pratinjau/staf');
        const overflow = await page.locator('section.on-primary p').first().evaluate((value) => value.scrollWidth - value.clientWidth);
        expect(overflow, `lebar ${width}`).toBeLessThanOrEqual(0);
    }
});

test('angka uang sejajar di kolom karena lebar digitnya sama', async ({ page }) => {
    await page.goto('/_pratinjau');
    await page.evaluate(() => document.fonts.ready);
    const widths = await page.evaluate(() => {
        const probe = document.createElement('span');
        probe.className = 'font-tabular';
        probe.style.position = 'absolute';
        document.body.append(probe);
        const measured = ['111111', '888888', '000000'].map((digits) => {
            probe.textContent = digits;
            return probe.getBoundingClientRect().width;
        });
        probe.remove();
        return measured;
    });
    expect(Math.max(...widths) - Math.min(...widths)).toBeLessThan(0.5);
});

test('font cadangan arab benar-benar dipakai contoh cadangan', async ({ page }) => {
    await page.goto('/_pratinjau');
    const family = await page.locator('.font-arabic-fallback').evaluate((el) => getComputedStyle(el).fontFamily);
    expect(family.startsWith('"Noto Naskh Arabic"')).toBe(true);
});

test('halaman publik beranda tetap tersedia', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
});

test('tepat satu kartu hero per layar portal dan staf', async ({ page }) => {
    for (const path of ['/_pratinjau/staf', '/_pratinjau/portal/wali', '/_pratinjau/portal/santri']) {
        await page.goto(path);
        await expect(page.locator('section.on-primary')).toHaveCount(1);
    }
});

test('tautan pratinjau antarpenampil berfungsi dan tombol lewati konten tersedia', async ({ page }) => {
    await page.goto('/_pratinjau');
    await page.getByRole('link', { name: 'Layout staf' }).click();
    await expect(page).toHaveURL(/_pratinjau\/staf$/);
    await page.getByRole('link', { name: 'Komponen' }).click();
    await page.getByRole('link', { name: 'Portal santri' }).click();
    await expect(page.getByRole('heading', { name: 'Beranda Santri' })).toBeVisible();

    await page.reload();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Lewati ke konten utama' })).toBeFocused();
    await expect(page.getByRole('link', { name: 'Lewati ke konten utama' })).toBeVisible();
});

test('elemen status menghormati prefers-reduced-motion', async ({ browser }) => {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    const page = await context.newPage();
    await page.goto(`http://127.0.0.1:${process.env.E2E_PORT || 8010}/_pratinjau`);
    const duration = await page.locator('.animate-pulse').first().evaluate((el) => getComputedStyle(el).animationDuration);
    expect(parseFloat(duration)).toBeLessThan(0.001);
    await context.close();
});
