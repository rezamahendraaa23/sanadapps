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
    await pageReady(page);
}

async function noOverflow(page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}

function wibLocal(hoursAhead) {
    return new Date(Date.now() + (7 + hoursAhead) * 3600 * 1000).toISOString().slice(0, 16);
}

test.describe.configure({ mode: 'serial' });

let examUrl;

test('admin: aktifkan modul ujian dan siapkan penugasan', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'admin@uji.test', 'sandi-uji-admin');
    await page.goto('/panel/pengaturan/modul');
    await pageReady(page);
    const section = page.locator('section', { hasText: 'Ujian online' }).first();
    const enable = section.getByRole('button', { name: 'Aktifkan' });
    if (await enable.count()) {
        await enable.click();
        await expect(section.getByRole('button', { name: 'Matikan' })).toBeVisible();
    }

    await page.goto('/panel/mapel');
    await pageReady(page);
    await page.getByRole('button', { name: 'Tambah Mapel' }).click();
    const form = page.getByRole('dialog', { name: 'Tambah mapel' });
    await form.getByLabel('Kode').fill('E2EU');
    await form.getByLabel('Nama').fill('Bahasa Arab Uji');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();
});

test('ustadz: bank soal dengan soal Arab, susun ujian lewat wizard, terbitkan', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'ustadz@uji.test', 'sandi-uji-ustadz', /\/ustadz$/);
    await page.goto('/ustadz/ujian/bank-soal');
    await pageReady(page);

    await page.getByRole('button', { name: 'Bank Baru' }).click();
    const bank = page.getByRole('dialog', { name: 'Bank soal baru' });
    await bank.getByLabel('Nama bank').fill('Bank Uji');
    await bank.getByLabel('Mapel').selectOption({ label: 'Bahasa Arab Uji' });
    await bank.getByRole('button', { name: 'Simpan' }).click();
    await expect(bank).toBeHidden();

    const form = page.getByRole('dialog', { name: 'Tambah soal' });
    await page.getByRole('button', { name: 'Tambah Soal' }).click();
    await form.getByLabel('Arah teks').selectOption('rtl');
    await form.getByLabel('Soal', { exact: true }).fill('مَا مَعْنَى كِتَاب؟');
    await form.getByLabel('Opsi A', { exact: true }).fill('Buku');
    await form.getByLabel('Opsi B', { exact: true }).fill('Pena');
    await form.getByLabel('Kunci A').check();
    await expect(form.getByText('Pratinjau tampilan santri')).toBeVisible();
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();

    await page.getByRole('button', { name: 'Tambah Soal' }).click();
    await form.getByLabel('Jenis', { exact: true }).selectOption('essay');
    await form.getByLabel('Arah teks').selectOption('ltr');
    await form.getByLabel('Soal', { exact: true }).fill('Jelaskan arti belajar.');
    await form.getByLabel('Poin').fill('4');
    await form.getByRole('button', { name: 'Simpan' }).click();
    await expect(form).toBeHidden();
    if (shots) await page.screenshot({ path: `${shots}/bank-soal-1280.png`, fullPage: true });

    await page.goto('/ustadz/ujian');
    await pageReady(page);
    await page.getByRole('button', { name: 'Buat Ujian' }).click();
    await expect(page).toHaveURL(/ujian\/[0-9a-f-]{36}\/susun/);
    await pageReady(page);
    await page.getByLabel('Judul').fill('Ulangan Uji');
    await page.getByLabel('Mapel').selectOption({ label: 'Bahasa Arab Uji' });
    await page.getByLabel('Dibuka').fill(wibLocal(-1));
    await page.getByLabel('Ditutup').fill(wibLocal(3));
    await page.getByRole('button', { name: 'Lanjut' }).click();

    await page.getByLabel('Bank soal').selectOption({ label: 'Bank Uji (2 soal)' });
    await expect(page.getByRole('checkbox')).toHaveCount(2);
    for (const box of await page.getByRole('checkbox').all()) {
        await box.check();
    }
    await expect(page.getByText('2 soal', { exact: false }).last()).toBeVisible();
    await page.getByRole('button', { name: 'Lanjut' }).click();
    await page.getByRole('button', { name: 'Lanjut' }).click();

    await page.getByRole('checkbox', { name: 'Kelas Uji 6' }).check();
    await expect(page.getByText(/1 peserta/)).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/wizard-1280.png`, fullPage: true });
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Terbitkan' }).click();
    await expect(page).toHaveURL(/ustadz\/ujian$/);
    await expect(page.getByText('Terbit', { exact: true })).toBeVisible();
    examUrl = await page.getByRole('link', { name: /Monitor/ }).getAttribute('href');
});

test('santri di HP: persiapan, mengerjakan, putus koneksi tidak menghilangkan jawaban, muat ulang tetap ada', async ({ page, context }) => {
    await page.setViewportSize({ width: 360, height: 640 });
    await loginStudent(page);
    await page.getByRole('link', { name: /Mulai/ }).first().click();
    await expect(page).toHaveURL(/persiapan$/);
    await pageReady(page);
    await expect(page.getByText('Koneksi tersambung')).toBeVisible();
    await expect(page.getByText('Penyimpanan HP siap')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Mulai Ujian' })).toBeDisabled();
    await page.getByLabel('Saya mengerjakan sendiri dengan jujur.').check();
    await expect(page.getByRole('button', { name: 'Mulai Ujian' })).toBeEnabled();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/ujian-persiapan-360.png`, fullPage: true });
    await page.getByRole('button', { name: 'Mulai Ujian' }).click();
    await expect(page).toHaveURL(/\/ujian\/[0-9a-f-]{36}$/);
    await page.evaluate(() => navigator.serviceWorker.ready);

    await expect(page.getByRole('timer', { name: 'Sisa waktu' })).toBeVisible();
    await noOverflow(page);

    const arab = page.getByRole('radio', { name: 'Buku' });
    if (await arab.count() === 0) {
        await page.getByRole('button', { name: 'Berikutnya' }).click();
    }
    await expect(page.locator('[lang=ar]').first()).toHaveAttribute('dir', 'rtl');
    if (shots) await page.screenshot({ path: `${shots}/ujian-soal-arab-360.png`, fullPage: true });
    await page.getByRole('radio', { name: 'Buku' }).click();
    await expect(page.getByText(/Tersimpan \d/)).toBeVisible();

    await context.setOffline(true);
    await expect(page.getByText(/Koneksi terputus/)).toBeVisible();
    await page.getByRole('radio', { name: 'Pena' }).click();
    await expect(page.getByText(/Offline, jawaban aman di HP/)).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/ujian-offline-360.png`, fullPage: true });

    await page.getByRole('button', { name: 'Daftar nomor soal' }).click();
    const grid = page.getByRole('dialog', { name: 'Daftar nomor soal' });
    await expect(grid.getByRole('button', { name: /terjawab/ })).toHaveCount(1);
    await expect(grid.getByRole('button', { name: /belum/ })).toHaveCount(1);
    await noOverflow(page);
    await grid.getByRole('button', { name: 'Tutup' }).click();

    page.on('dialog', (dialog) => dialog.accept());
    await page.reload();
    await page.waitForFunction(() => window.Alpine);
    await expect(page.getByRole('timer', { name: 'Sisa waktu' })).toBeVisible();
    await page.getByRole('button', { name: 'Daftar nomor soal' }).click();
    await expect(page.getByRole('dialog', { name: 'Daftar nomor soal' }).getByRole('button', { name: /terjawab/ })).toHaveCount(1);
    await page.getByRole('dialog', { name: 'Daftar nomor soal' }).getByRole('button', { name: 'Tutup' }).click();

    await context.setOffline(false);
    await expect(page.getByText(/Koneksi terputus/)).toBeHidden();
    await expect(page.getByText('Tersinkron')).toBeVisible();

    const essayVisible = await page.getByRole('textbox', { name: 'Jawaban' }).count();
    if (! essayVisible) {
        const next = page.getByRole('button', { name: 'Berikutnya' });
        if (await next.count()) await next.click(); else await page.getByRole('button', { name: 'Sebelumnya' }).click();
    }
    await page.getByRole('textbox', { name: 'Jawaban' }).fill('Belajar adalah menuntut ilmu.');
    await expect(page.getByText(/karakter/)).toBeVisible();
    await expect(page.getByText(/Tersimpan \d/)).toBeVisible({ timeout: 10_000 });

    await page.getByRole('button', { name: 'Daftar nomor soal' }).click();
    await page.getByRole('button', { name: 'Tinjau dan Kumpulkan' }).click();
    const review = page.getByRole('alertdialog', { name: 'Kumpulkan ujian' });
    await expect(review.getByText('Kumpulkan sekarang?', { exact: true })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/ujian-tinjau-360.png`, fullPage: true });
    await review.getByRole('button', { name: 'Kumpulkan', exact: true }).click();
    await expect(page).toHaveURL(/selesai$/);
    await expect(page.getByRole('heading', { name: 'Jawaban terkirim' })).toBeVisible();
    await noOverflow(page);
    if (shots) await page.screenshot({ path: `${shots}/ujian-selesai-360.png`, fullPage: true });
    expect(await page.evaluate(() => new Promise((resolve) => { const r = indexedDB.open('sanad-ujian', 1); r.onsuccess = () => { const c = r.result.transaction('attempts').objectStore('attempts').count(); c.onsuccess = () => resolve(c.result); }; }))).toBe(0);
});

test('ustadz: monitor, koreksi esai, nilai akhir mencerminkan jawaban setelah sinkron', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await loginStaff(page, 'ustadz@uji.test', 'sandi-uji-ustadz', /\/ustadz$/);
    await page.goto(examUrl);
    await pageReady(page);
    await expect(page.getByRole('cell', { name: 'Santri Tiga' })).toBeVisible();
    await expect(page.getByRole('cell', { name: /Selesai/ })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/ujian-monitor-1280.png`, fullPage: true });

    await page.getByRole('link', { name: 'Hasil' }).click();
    await expect(page).toHaveURL(/hasil$/);
    await pageReady(page);
    await expect(page.getByText('Belajar adalah menuntut ilmu.')).toBeVisible();
    await page.getByLabel(/Poin \(maksimal 4\)/).fill('9');
    await page.getByRole('button', { name: 'Simpan dan Berikutnya' }).click();
    await expect(page.getByText('Poin harus 0 sampai 4.')).toBeVisible();
    await page.getByLabel(/Poin \(maksimal 4\)/).fill('4');
    await page.getByRole('button', { name: 'Simpan dan Berikutnya' }).click();
    await expect(page.getByRole('heading', { name: 'Koreksi esai' })).toBeHidden();
    // Pilihan terakhir "Pena" salah; bila jawaban offline hilang, "Buku" yang benar akan tersimpan dan nilainya 100.
    await expect(page.getByRole('cell', { name: '80' })).toBeVisible();
    if (shots) await page.screenshot({ path: `${shots}/ujian-hasil-1280.png`, fullPage: true });

    await page.setViewportSize({ width: 360, height: 640 });
    for (const url of ['/ustadz/ujian', '/ustadz/ujian/bank-soal', examUrl]) {
        await page.goto(url);
        await noOverflow(page);
    }
});
