import { expect } from '@playwright/test';

/**
 * Menu yang tinggal di ruang kerja hanya tampil setelah ruang kerjanya dibuka.
 * Nama ruang kerja harus sama dengan label grup di config/sanad.php.
 */
const WORKSPACE = {
    Tagihan: 'Keuangan',
    Pembayaran: 'Keuangan',
    'Verifikasi Transfer': 'Keuangan',
    Tunggakan: 'Keuangan',
    Dompet: 'Keuangan',
    'Laporan Kas': 'Keuangan',
    'Jenis Tagihan': 'Keuangan',
    Kasir: 'Mart & Kasir',
    'Mart & Warung': 'Mart & Kasir',
    Pencairan: 'Mart & Kasir',
    Periode: 'PPDB',
    Pendaftar: 'PPDB',
    Seleksi: 'PPDB',
    'Jadwal Sholat': 'Masjid & Layar TV',
    'Konten Layar': 'Masjid & Layar TV',
    'Layar TV': 'Masjid & Layar TV',
};

const sidebar = (page) => page.getByRole('navigation', { name: /^Menu / }).first();

/**
 * Membuka menu di sidebar seperti pengguna: langsung bila terlihat, atau lewat pintu ruang kerjanya.
 */
export async function bukaMenu(page, label) {
    const link = () => sidebar(page).getByRole('link', { name: label, exact: true });
    if (await link().count()) {
        await link().click();
        return;
    }

    const workspace = WORKSPACE[label];
    if (!workspace) throw new Error(`Menu "${label}" tidak ada di sidebar dan tidak terdaftar di ruang kerja mana pun.`);

    // Sedang di ruang kerja lain: pulang dulu ke menu utama.
    const home = sidebar(page).getByRole('link', { name: 'Menu utama', exact: true });
    if (await home.count()) {
        await home.click();
        await expect(sidebar(page).getByRole('heading', { name: 'Ruang kerja' })).toBeVisible();
    }

    await sidebar(page).getByRole('link', { name: new RegExp(`^${workspace.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')} `) }).click();
    await expect(page.getByRole('navigation', { name: `Menu ${workspace}` }).first()).toBeVisible();
    if (await link().getAttribute('aria-current') !== 'page') await link().click();
}

/**
 * Pintu ruang kerja di sidebar utama, misalnya untuk memastikan peran tertentu tidak melihatnya.
 */
export function pintuRuangKerja(page, workspace) {
    return sidebar(page).getByRole('link', { name: new RegExp(`^${workspace.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')} `) });
}
