import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { LOG_FILE } from './global-setup.js';

const shots = process.env.E2E_SCREENSHOTS;
const DAYS = ['Ahad', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function wibToday() {
    const now = new Date(Date.now() + 7 * 3600 * 1000);
    return { iso: now.getUTCDay() === 0 ? 7 : now.getUTCDay(), name: DAYS[now.getUTCDay()], hour: now.getUTCHours() };
}

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

test('admin akademik: mapel, penugasan, jadwal dengan deteksi bentrok', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');

    await page.goto('/panel/kelas-asrama?tab=terms');
    await pageReady(page);
    const activate = page.getByRole('row', { name: /Term Uji/ }).getByRole('button', { name: /Jadikan aktif/ });
    if (await activate.count()) {
        await activate.click();
        await expect(page.getByText('Periode aktif diganti.')).toBeVisible();
    }

    await page.goto('/panel');
    await page.getByRole('link', { name: 'Mapel & Penugasan' }).click();
    await pageReady(page);
    await page.getByRole('button', { name: 'Tambah Mapel' }).click();
    const form = page.getByRole('dialog', { name: 'Tambah mapel' });
    await form.getByLabel('Kode').fill('E2EF');
    await form.getByLabel('Nama').fill('Fiqih Uji');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();
    await expect(page.getByRole('cell', { name: 'Fiqih Uji', exact: true })).toBeVisible();

    await page.getByRole('tab', { name: 'Penugasan' }).click();
    await expect(page.getByRole('combobox', { name: 'Ustadz' })).toBeVisible();
    await page.getByRole('combobox', { name: 'Ustadz' }).selectOption({ label: 'Ustadz Uji' });
    await page.getByRole('combobox', { name: 'Mapel' }).selectOption({ label: 'Fiqih Uji' });
    await page.getByRole('combobox', { name: 'Kelas' }).selectOption({ label: 'Kelas Uji 6' });
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByRole('cell', { name: 'Kelas Uji 6' })).toBeVisible();

    await page.getByRole('tab', { name: 'Komponen Nilai' }).click();
    await page.getByLabel('Nama komponen').fill('Akhir');
    await page.getByLabel('Bobot (%)').fill('100');
    await page.getByRole('button', { name: 'Tambah' }).click();
    await expect(page.getByText('Total bobot aktif 100%')).toBeVisible();

    const today = wibToday();
    await page.goto('/panel/jadwal');
    await pageReady(page);
    await page.getByRole('button', { name: `Tambah jadwal ${today.name}` }).click();
    const schedule = page.getByRole('dialog', { name: 'Tambah jadwal' });
    await schedule.getByLabel('Mulai').fill('00:00');
    await schedule.getByLabel('Selesai').fill('23:59');
    await schedule.getByLabel('Kelas').selectOption({ label: 'Kelas Uji 6' });
    await schedule.getByLabel('Mapel').selectOption({ label: 'Fiqih Uji' });
    await schedule.getByLabel('Ustadz').selectOption({ label: 'Ustadz Uji' });
    await schedule.getByRole('button', { name: 'Simpan' }).click();
    await expect(schedule).toBeHidden();
    await expect(page.getByRole('button', { name: /Fiqih Uji/ })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/jadwal-1280.png`, fullPage: true });

    await page.getByRole('button', { name: `Tambah jadwal ${today.name}` }).click();
    await schedule.getByLabel('Mulai').fill('08:00');
    await schedule.getByLabel('Selesai').fill('09:00');
    await schedule.getByLabel('Kelas').selectOption({ label: 'Kelas Uji 6' });
    await schedule.getByLabel('Mapel').selectOption({ label: 'Fiqih Uji' });
    await schedule.getByRole('button', { name: 'Simpan' }).click();
    await expect(schedule.getByText(/Bentrok: Kelas Uji 6/)).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/jadwal-bentrok-1280.png` });
    await schedule.getByRole('button', { name: 'Batal' }).click();
});

test('ustadz di HP: masuk ke portal, absen dengan draf lokal saat putus, simpan', async ({ page, context }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginStaff(page, 'ustadz@uji.test', 'sandi-uji-ustadz', /\/ustadz$/);
    await expect(page.getByText('Fiqih Uji').first()).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/ustadz-beranda-360.png`, fullPage: true });

    await page.getByRole('link', { name: 'Mulai Absensi' }).click();
    await expect(page).toHaveURL(/ustadz\/absensi$/);
    await pageReady(page);
    await page.getByRole('button', { name: /Mulai Absensi/ }).click();
    await expect(page).toHaveURL(/ustadz\/absensi\/[0-9a-f-]{36}$/);
    await pageReady(page);
    await noOverflow(page);

    const row = page.getByRole('radiogroup', { name: 'Status Santri Tiga' });
    await expect(row.getByRole('radio', { name: 'Hadir' })).toHaveAttribute('aria-checked', 'true');

    await context.setOffline(true);
    await expect(page.getByText(/Tidak tersambung. Perubahan tersimpan/)).toBeVisible();
    await row.getByRole('radio', { name: 'Alpa' }).click();
    await expect(row.getByRole('radio', { name: 'Alpa' })).toHaveAttribute('aria-checked', 'true');
    if (shots) await page.screenshot({ path: `${shots}/ustadz-absen-putus-360.png`, fullPage: true });
    await context.setOffline(false);
    await expect(page.getByText(/Tidak tersambung. Perubahan tersimpan/)).toBeHidden();

    await page.reload();
    await pageReady(page);
    await expect(page.getByRole('radiogroup', { name: 'Status Santri Tiga' }).getByRole('radio', { name: 'Alpa' })).toHaveAttribute('aria-checked', 'true');

    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await expect(page.getByText('Tersimpan. Masih bisa dikoreksi.')).toBeVisible();
    expect(await page.evaluate(() => Object.keys(localStorage).filter((key) => key.startsWith('sanad-absen-')).length)).toBe(0);
});

test('ustadz memberi nilai: nilai salah ditolak, nilai benar tersimpan', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginStaff(page, 'ustadz@uji.test', 'sandi-uji-ustadz', /\/ustadz$/);
    await page.getByRole('navigation', { name: 'Navigasi portal' }).getByRole('link', { name: 'Nilai' }).click();
    await expect(page).toHaveURL(/ustadz\/nilai/);
    await pageReady(page);

    await page.getByRole('button', { name: 'Tambah Penilaian' }).click();
    const form = page.getByRole('dialog', { name: 'Tambah penilaian' });
    await form.getByLabel('Judul').fill('Ujian Akhir');
    await form.getByLabel('Komponen').selectOption({ label: 'Akhir' });
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();

    await page.getByLabel('Santri Tiga').fill('101');
    await page.getByRole('button', { name: 'Simpan Nilai' }).click();
    await expect(page.getByText('Harus 0 sampai 100.')).toBeVisible();
    await page.getByLabel('Santri Tiga').fill('88');
    await page.getByRole('button', { name: 'Simpan Nilai' }).click();
    await expect(page.getByText('1 nilai tersimpan.')).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/ustadz-nilai-360.png`, fullPage: true });
});

test('admin: rekap absensi, buat draf rapor, terbitkan, PDF terunduh', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');

    await page.goto('/panel/absensi/rekap');
    await pageReady(page);
    await expect(page.getByRole('cell', { name: 'Santri Tiga' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/rekap-absensi-1280.png`, fullPage: true });

    await page.goto('/panel/rapor');
    await pageReady(page);
    await page.getByRole('combobox', { name: 'Kelas' }).selectOption({ label: 'Kelas Uji 6' });
    await page.getByRole('button', { name: 'Buat Draf Rapor' }).click();
    await expect(page.getByRole('cell', { name: /^Santri Tiga 2026003/ })).toBeVisible();
    await page.getByRole('button', { name: /Terbitkan 1 Rapor/ }).click();
    const dialog = page.getByRole('dialog', { name: /Terbitkan/ });
    await dialog.getByRole('button', { name: 'Terbitkan', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('cell', { name: 'Terbit' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/rapor-1280.png`, fullPage: true });

    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: /PDF/ }).click();
    expect((await download).suggestedFilename()).toContain('rapor-');
});

test('wali melihat alpa di notifikasi, jadwal, absensi, dan rapor anaknya', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await page.goto('/masuk');
    await pageReady(page);
    await page.getByLabel('Nomor WhatsApp').fill('081234567893');
    await page.getByRole('button', { name: 'Kirim Kode' }).click();
    await expect(page).toHaveURL(/masuk\/otp$/);
    await pageReady(page);
    const code = [...readFileSync(LOG_FILE, 'utf8').matchAll(/OTP 6281234567893: (\d{6})/g)].at(-1)[1];
    await page.getByLabel('Kode OTP').fill(code);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/wali$/);
    await expect(page.getByRole('link', { name: /Notifikasi, \d+ belum dibaca/ })).toBeVisible();
    await expect(page.getByText('Fiqih Uji').first()).toBeVisible();

    await page.getByRole('navigation', { name: 'Navigasi portal' }).getByRole('link', { name: 'Perkembangan' }).click();
    await expect(page).toHaveURL(/wali\/perkembangan/);
    await pageReady(page);
    await expect(page.getByText('Fiqih Uji').first()).toBeVisible();
    await noOverflow(page);

    await page.getByRole('tab', { name: 'Absensi' }).click();
    await expect(page.getByText('0%')).toBeVisible();
    await page.getByRole('tab', { name: 'Nilai' }).click();
    await expect(page.getByRole('heading', { name: /Rapor/ })).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/wali-rapor-360.png`, fullPage: true });
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Unduh Rapor' }).click();
    await download;
});

test('santri melihat jadwal; layar panel aman di HP', async ({ page, browser, baseURL }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/panel/mapel', '/panel/jadwal', '/panel/absensi', '/panel/absensi/rekap', '/panel/nilai', '/panel/rapor']) {
        await page.goto(url);
        await noOverflow(page);
    }

    const student = await browser.newContext({ baseURL, viewport: { width: 360, height: 640 } });
    const studentPage = await student.newPage();
    await studentPage.goto('/masuk?sebagai=santri');
    await pageReady(studentPage);
    await studentPage.getByLabel('NIS').fill('2026003');
    await studentPage.getByLabel('Sandi', { exact: true }).fill('sandi-uji-tiga');
    await studentPage.getByRole('button', { name: 'Masuk', exact: true }).click();
    await expect(studentPage).toHaveURL(/\/santri$/);
    await expect(studentPage.getByText('Fiqih Uji').first()).toBeVisible();
    await studentPage.getByRole('navigation', { name: 'Navigasi portal' }).getByRole('link', { name: 'Jadwal' }).click();
    await expect(studentPage).toHaveURL(/santri\/jadwal/);
    await expect(studentPage.getByText(/sedang berlangsung/)).toBeVisible();
    if (shots) await studentPage.screenshot({ path: `${shots}/santri-jadwal-360.png`, fullPage: true });
    await student.close();
});
