import { expect, test } from '@playwright/test';

const shots = process.env.E2E_SCREENSHOTS;

async function pageReady(page) {
    await page.waitForFunction(() => window.Alpine && [...document.querySelectorAll('[role=dialog]')].every((el) => el.style.display === 'none'));
}

test('admin menyusun struktur dari kotak atasan, garis tersambung sendiri', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/masuk?sebagai=staf');
    await pageReady(page);
    await page.getByLabel('Email').fill('admin@uji.test');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-admin');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);

    await page.goto('/panel/struktur?bagan=kesantrian');
    await pageReady(page);
    const add = async (parent, title, name) => {
        await page.getByRole('button', { name: `Tambah bawahan ${parent}` }).click();
        const form = page.getByRole('dialog', { name: 'Tambah jabatan' });
        await form.getByLabel('Jabatan').fill(title);
        if (name) await form.getByLabel('Nama', { exact: true }).fill(name);
        await form.getByRole('button', { name: 'Simpan' }).click();
        await expect(form).toBeHidden();
        await expect(page.getByText(title, { exact: true })).toBeVisible();
    };
    await page.getByRole('button', { name: 'Ubah Rois' }).click();
    const edit = page.getByRole('dialog', { name: 'Ubah jabatan' });
    await edit.getByLabel('Nama', { exact: true }).fill('Ust. Ahmad Fauzi');
    await edit.getByRole('button', { name: 'Simpan' }).click();
    await expect(edit).toBeHidden();
    await add('Rois', 'Wakil Rois', 'Ust. Hasan');
    await add('Rois', 'Sekretaris', 'Ust. Imam');
    await add('Rois', 'Bendahara', null);
    await add('Wakil Rois', 'Bagian Keamanan', 'Fulan');
    await add('Wakil Rois', 'Bagian Kebersihan', 'Abdullah');
    await add('Sekretaris', 'Bagian Bahasa', 'Zaid');
    await expect(page.getByText('Belum diisi')).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/struktur-1440.png`, fullPage: true });
});
