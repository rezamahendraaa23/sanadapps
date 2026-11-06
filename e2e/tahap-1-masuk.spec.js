import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { LOG_FILE } from './global-setup.js';

const shots = process.env.E2E_SCREENSHOTS;

function latestOtp() {
    const matches = [...readFileSync(LOG_FILE, 'utf8').matchAll(/OTP 6281234567890: (\d{6})/g)];
    return matches.at(-1)?.[1];
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}

test('wali: nomor, OTP salah, OTP benar, beranda menampilkan anak, keluar', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/masuk');
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/masuk-wali-360.png`, fullPage: true });

    await page.getByLabel('Nomor WhatsApp').fill('12');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(page.getByRole('alert')).toContainText('Nomor WhatsApp belum benar');
    await expect(page.getByLabel('Nomor WhatsApp')).toBeFocused();

    await page.getByLabel('Nomor WhatsApp').fill('0812-3456-7890');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(page).toHaveURL(/masuk\/otp$/);
    await noOverflow(page);
    await expect(page.getByRole('button', { name: /Kirim ulang dalam/ })).toBeDisabled();

    const right = latestOtp();
    const wrong = right === '000000' ? '000001' : '000000';
    await page.getByLabel('Kode OTP').fill(wrong);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page.getByRole('alert')).toContainText('OTP salah, sisa 2 percobaan');
    if (shots) await page.screenshot({ path: `${shots}/otp-salah-360.png`, fullPage: true });

    await page.getByLabel('Kode OTP').fill(right);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/wali$/);
    await expect(page.getByText('Santri Uji')).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-beranda-360.png`, fullPage: true });

    await page.getByRole('button', { name: 'Keluar' }).click();
    await expect(page).toHaveURL(/\/masuk$/);
    await page.goto('/wali');
    await expect(page).toHaveURL(/\/masuk$/);
});

test('santri masuk dengan NIS, sandi bisa ditampilkan', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/masuk');
    await page.getByRole('tab', { name: 'Santri' }).click();
    await expect(page.getByRole('tab', { name: 'Santri' })).toHaveAttribute('aria-selected', 'true');

    await page.getByLabel('NIS').fill('2026001');
    const password = page.getByLabel('Sandi', { exact: true });
    await password.fill('keliru');
    await page.getByRole('button', { name: 'Tampilkan sandi' }).click();
    await expect(password).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: 'Sembunyikan sandi' }).click();
    await expect(password).toHaveAttribute('type', 'password');

    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page.getByRole('alert')).toContainText('NIS atau sandi salah.');

    await password.fill('sandi-uji-santri');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/santri$/);
    await expect(page.getByText('Santri Uji').first()).toBeVisible();
});

test('staf masuk dengan email, menu akun berfungsi, lalu keluar', async ({ page }) => {
    for (const width of [360, 1280]) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto('/masuk?sebagai=staf');
        await page.getByLabel('Email').fill('admin@uji.test');
        await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-admin');
        await page.getByRole('button', { name: 'Masuk', exact: true }).click();
        await expect(page).toHaveURL(/\/panel$/);
        await noOverflow(page);
        if (shots) await page.screenshot({ path: `${shots}/staf-panel-${width}.png` });

        await page.getByRole('button', { name: /Admin Uji/ }).click();
        await expect(page.getByText('admin', { exact: true })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('button', { name: 'Keluar' })).toBeHidden();

        await page.getByRole('button', { name: /Admin Uji/ }).click();
        await page.getByRole('button', { name: 'Keluar' }).click();
        await expect(page).toHaveURL(/\/masuk$/);
    }
});

test('lupa sandi tidak membedakan email terdaftar', async ({ page }) => {
    await page.goto('/masuk?sebagai=staf');
    await page.getByRole('link', { name: 'Lupa sandi?' }).click();
    await expect(page).toHaveURL(/lupa-sandi$/);
    await page.getByLabel('Email akun').fill('tidak-ada@uji.test');
    await page.getByRole('button', { name: 'Kirim Tautan' }).click();
    await expect(page.getByText('Bila email itu terdaftar')).toBeVisible();
    await page.getByRole('link', { name: 'Kembali ke halaman masuk' }).click();
    await expect(page.getByRole('tab', { name: 'Staf' })).toHaveAttribute('aria-selected', 'true');
});
