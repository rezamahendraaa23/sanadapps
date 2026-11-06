import { expect, test } from '@playwright/test';

const shots = process.env.E2E_SCREENSHOTS;

async function login(page) {
    await page.goto('/masuk?sebagai=staf');
    await page.getByLabel('Email').fill('admin@uji.test');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-admin');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}

test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await login(page);
});

test('profil: nama dan warna berubah di seluruh aplikasi, warna gagal ditolak', async ({ page }) => {
    await page.getByRole('link', { name: 'Profil & Branding' }).click();
    await expect(page).toHaveURL(/pengaturan\/profil$/);

    await page.getByLabel('Warna utama').fill('#FFD500');
    await expect(page.getByText('belum lolos 4,5:1')).toBeVisible();
    await page.getByLabel('Nama pesantren').fill('Pesantren E2E');
    await page.getByRole('button', { name: 'Simpan Profil' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'terlalu terang' })).toBeVisible();
    await expect(page.getByLabel('Warna utama')).toBeFocused();

    await page.getByLabel('Warna utama').fill('#1D3F72');
    await expect(page.getByText(/, lolos/)).toBeVisible();
    await page.getByRole('button', { name: 'Simpan Profil' }).click();
    await expect(page.getByText('Profil pesantren tersimpan.')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/profil-1280.png`, fullPage: true });

    await page.reload();
    const primary = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim());
    expect(primary).toBe('#1D3F72');

    await page.getByLabel('Warna utama').fill('#0B5D45');
    await page.getByRole('button', { name: 'Simpan Profil' }).click();
    await expect(page.getByText('Profil pesantren tersimpan.')).toBeVisible();
});

test('modul: dependensi ditolak dengan alasan, matikan lewat dialog, menu ikut hilang', async ({ page }) => {
    await page.getByRole('link', { name: 'Modul', exact: true }).click();
    await noOverflow(page);

    const pos = page.locator('section', { hasText: 'Mart & Warung' });
    await pos.getByRole('button', { name: 'Aktifkan' }).click();
    await expect(pos.getByRole('alert')).toContainText('Aktifkan dulu: Dompet uang saku.');
    if (shots) await page.screenshot({ path: `${shots}/modul-1280.png` });

    const academic = page.locator('section', { hasText: 'Ujian online' });
    await academic.getByRole('button', { name: 'Aktifkan' }).click();
    await expect(page.getByText('Ujian online aktif.')).toBeVisible();
    await expect(academic.getByRole('button', { name: 'Matikan' })).toBeVisible();

    await academic.getByRole('button', { name: 'Matikan' }).click();
    const dialog = page.getByRole('dialog', { name: 'Matikan modul ini?' });
    await expect(dialog).toContainText('Data tidak dihapus');
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(academic.getByRole('button', { name: 'Matikan' })).toBeVisible();

    await academic.getByRole('button', { name: 'Matikan' }).click();
    await dialog.getByRole('button', { name: 'Matikan Modul' }).click();
    await expect(academic.getByRole('button', { name: 'Aktifkan' })).toBeVisible();

    await page.getByLabel('Cari modul').fill('tahfidz');
    await expect(page.getByRole('heading', { name: 'Tahfidz & mutabaah' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Keuangan' })).toBeHidden();
});

test('integrasi: kunci tersamar setelah disimpan', async ({ page }) => {
    await page.getByRole('link', { name: 'Integrasi', exact: true }).click();
    await page.getByLabel('Token').fill('token-rahasia-e2e-4321');
    await page.getByRole('button', { name: 'Simpan Fonnte' }).click();
    await expect(page.getByText('Tersimpan: ••••4321')).toBeVisible();
    await expect(page.getByLabel('Token')).toHaveValue('');
    expect(await page.content()).not.toContain('token-rahasia-e2e');
    await noOverflow(page);
});

test('audit log: mencatat perubahan, rincian terbuka, tanpa kunci rahasia', async ({ page }) => {
    await page.getByRole('link', { name: 'Audit Log' }).click();
    await page.getByLabel('Entitas').selectOption('Integration');
    await expect(page.locator('tbody tr').first()).toContainText('Integration');
    await page.locator('tbody tr').first().getByRole('button', { name: /Lihat/ }).click();
    const dialog = page.getByRole('dialog', { name: 'Rincian perubahan' });
    await expect(dialog).toBeVisible();
    expect(await dialog.textContent()).not.toContain('token-rahasia');
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    if (shots) await page.screenshot({ path: `${shots}/audit-1280.png` });
});

test('semua layar pengaturan tanpa scroll samping di HP', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    for (const path of ['profil', 'modul', 'integrasi', 'audit-log']) {
        await page.goto(`/panel/pengaturan/${path}`);
        await noOverflow(page);
        if (shots) await page.screenshot({ path: `${shots}/pengaturan-${path}-360.png` });
    }
});
