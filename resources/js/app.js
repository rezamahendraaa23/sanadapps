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
