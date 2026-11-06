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

// Livewire mengirim detail sebagai array berisi satu objek saat memakai parameter bernama.
window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    window.Alpine?.store('toasts').push(detail);
});
