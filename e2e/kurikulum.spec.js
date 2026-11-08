import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const shots = process.env.E2E_SCREENSHOTS;
const data = (step) => execFileSync(process.env.PHP_BIN || 'php', ['e2e/kurikulum-data.php', step], {
    env: { ...process.env, DB_DATABASE: process.env.E2E_DB || 'sanad_e2e', CACHE_STORE: 'array' },
});

async function pageReady(page) {
    await page.waitForFunction(() => window.Livewire && window.Alpine && [...document.querySelectorAll('[role=dialog]')].every((el) => el.style.display === 'none'));
}

test("fase serentak, Program Ramadan menggeser fase, santri Ta'aruf dipetakan ke jalur kitab dan tahfidz", async ({ page }) => {
    test.setTimeout(180_000);
    await page.setViewportSize({ width: 1280, height: 800 });
    data('siapkan');

    await page.goto('/masuk?sebagai=staf');
    await pageReady(page);
    await page.getByLabel('Email').fill('admin@uji.test');
    await page.getByLabel('Sandi', { exact: true }).fill('sandi-uji-admin');
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(page).toHaveURL(/\/panel$/);

    // Tiga kelas mulai serentak di fasenya masing-masing.
    await page.goto('/panel/kurikulum?tab=rombongan');
    await pageReady(page);
    const plan = page.locator('form', { has: page.getByRole('table', { name: 'Rencana fase tiap kelas' }) });
    await plan.getByLabel('Mulai').fill('2026-11-09');
    for (const [room, phase] of [["Kelas 7 Ta'aruf", "Tarbiyatut Ta'aruf"], ['Kitab Dasar 1', 'Kitab Dasar'], ['Tahfidz Dasar 1', 'Tahfidz Dasar']]) {
        const curriculum = phase.startsWith('Tarbiyat') ? "Program Ta'aruf" : phase.startsWith('Kitab') ? 'Jalur Kitab' : 'Jalur Tahfidz';
        await plan.getByLabel(`Fase berikutnya untuk ${room}`).selectOption({ label: `${curriculum}: ${phase}` });
    }
    if (shots) await page.screenshot({ path: `${shots}/kurikulum-serentak-1280.png`, fullPage: true });
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Mulai Fase' }).click();
    await expect(page.getByText('3 kelas mulai fase pada 9 November 2026.')).toBeVisible();
    // 12 minggu sejak 9 November selesai 31 Januari.
    await expect(page.getByText('9 Nov 2026 sampai 31 Jan 2027').first()).toBeVisible();

    // Program Ramadan sepuluh hari di tengah fase menggeser semua tanggal selesai.
    await page.getByRole('tab', { name: 'Jeda Fase' }).click();
    await page.getByRole('button', { name: 'Tambah Jeda' }).click();
    const form = page.getByRole('dialog', { name: 'Tambah jeda fase' });
    await form.getByLabel('Nama').fill('Program Ramadan uji');
    await form.getByLabel('Mulai').fill('2026-12-01');
    await form.getByLabel('Selesai').fill('2026-12-10');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Tanggal selesai fase 3 kelas disesuaikan.', { exact: false })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Ubah Program Ramadan uji' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/kurikulum-jeda-1280.png`, fullPage: true });

    await page.getByRole('tab', { name: 'Rombongan' }).click();
    await expect(page.getByText('9 Nov 2026 sampai 10 Feb 2027').first()).toBeVisible();

    // Hasil Ta'aruf lulus; admin memetakan tiap santri ke jalurnya.
    data('lulus');
    await page.getByRole('button', { name: "Evaluasi Kelas 7 Ta'aruf" }).click();
    await pageReady(page);
    await page.getByLabel('Isi semua dengan').selectOption({ label: 'Kitab Dasar 1 (Jalur Kitab, Kitab Dasar)' });
    await page.getByRole('button', { name: 'Isi Semua' }).click();
    await page.getByLabel('Kelas tujuan Hasan Tahfidz').selectOption({ label: 'Tahfidz Dasar 1 (Jalur Tahfidz, Tahfidz Dasar)' });
    await page.getByLabel('Kelas tujuan Zaid Tetap').selectOption({ label: 'Tetap di kelas ini' });
    if (shots) await page.screenshot({ path: `${shots}/kurikulum-pemetaan-1280.png`, fullPage: true });
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Pindahkan' }).click();
    await expect(page.getByText('2 santri dipindahkan ke kelas tujuannya.')).toBeVisible();
    await expect(page.getByLabel('Kelas tujuan Zaid Tetap')).toBeVisible();
    await expect(page.getByLabel('Kelas tujuan Fajar Kitab')).toHaveCount(0);
});
