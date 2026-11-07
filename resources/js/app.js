document.addEventListener('alpine:init', () => {
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
let navigating = false;
document.addEventListener('livewire:navigating', () => {
    navigating = true;
});
document.addEventListener('livewire:navigated', () => {
    if (!navigating) return;
    navigating = false;
    document.getElementById('konten')?.focus({ preventScroll: true });
});
