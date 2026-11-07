import { registerAdmission } from './admission';
import { registerAttendanceCamera } from './attendance';
import { registerExam } from './exam';

document.addEventListener('alpine:init', () => {
    registerExam(window.Alpine);
    registerAdmission(window.Alpine);
    registerAttendanceCamera(window.Alpine);

    // Kamera untuk QR dinamis. BarcodeDetector ada di Chrome Android; di browser lain tombolnya tidak tampil dan kasir memakai reader.
    window.Alpine.data('qrScanner', (formats = ['qr_code']) => ({
        supported: 'BarcodeDetector' in window && !!navigator.mediaDevices?.getUserMedia,
        running: false,
        problem: '',
        stream: null,
        async toggle() {
            if (this.running) return this.stop();
            this.problem = '';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.running = true;
                this.detect(new window.BarcodeDetector({ formats }));
            } catch {
                this.problem = 'Kamera tidak bisa dibuka. Izinkan akses kamera, atau pakai reader.';
                this.stop();
            }
        },
        async detect(detector) {
            if (!this.running) return;
            try {
                const [code] = await detector.detect(this.$refs.video);
                if (code?.rawValue) {
                    this.stop();
                    this.$dispatch('qr-found', code.rawValue);
                    return;
                }
            } catch {
                // Bingkai belum siap; dicoba lagi pada putaran berikutnya.
            }
            setTimeout(() => this.detect(detector), 250);
        },
        stop() {
            this.running = false;
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
        },
        destroy() {
            this.stop();
        },
    }));

    window.Alpine.store('toasts', {
        items: [],
        nextId: 1,

        push({ message, tone = 'info', duration = 4000 }) {
            const id = this.nextId++;
            this.items.push({ id, message, tone });
            setTimeout(() => this.dismiss(id), duration);
        },

        dismiss(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    });
});

// Setelah form terkirim dan gagal validasi, fokus pindah ke isian pertama yang salah (PRD UI/UX 7).
let submitted = false;
document.addEventListener('submit', () => {
    submitted = true;
}, true);

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('commit', ({ succeed }) => {
        succeed(() => setTimeout(() => {
            if (!submitted) return;
            submitted = false;
            document.querySelector('[aria-invalid="true"]')?.focus();
        }));
    });
});

// Livewire mengirim detail sebagai array berisi satu objek saat memakai parameter bernama.
window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    window.Alpine?.store('toasts').push(detail);
});

// Tab: sorotan pindah seketika, tanpa menunggu jawaban server.
document.addEventListener('click', (event) => {
    const tab = event.target.closest('[role="tab"][wire\\:click]');
    if (!tab) return;
    tab.closest('[role="tablist"]')?.querySelectorAll('[role="tab"]').forEach((item) => {
        item.setAttribute('aria-selected', item === tab ? 'true' : 'false');
    });
});

// Pindah halaman tanpa muat ulang: fokus dan pembaca layar ikut pindah ke konten baru.
// Menu samping tetap di posisi gulirnya saat pindah halaman, juga setelah muat ulang.
const KEEP_SCROLL = 'sanad-scroll-menu';

function visibleMenu() {
    return [...document.querySelectorAll('[data-keep-scroll]')].find((element) => element.offsetParent !== null);
}

function rememberMenuScroll() {
    const menu = visibleMenu();
    if (menu) sessionStorage.setItem(KEEP_SCROLL, String(menu.scrollTop));
}

function restoreMenuScroll() {
    const menu = visibleMenu();
    const saved = Number(sessionStorage.getItem(KEEP_SCROLL) ?? 0);
    if (menu && saved) menu.scrollTop = saved;
}

document.addEventListener('livewire:navigating', rememberMenuScroll);
document.addEventListener('livewire:navigated', restoreMenuScroll);
window.addEventListener('pagehide', rememberMenuScroll);

let navigating = false;
document.addEventListener('livewire:navigating', () => {
    navigating = true;
});
document.addEventListener('livewire:navigated', () => {
    if (!navigating) return;
    navigating = false;
    document.getElementById('konten')?.focus({ preventScroll: true });
});
