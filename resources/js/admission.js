// Unggah dokumen PPDB: foto dikecilkan di HP dulu agar hemat kuota (PPD-03).
const MAX_SIDE = 1600;

async function shrink(file) {
    if (! ['image/jpeg', 'image/png'].includes(file.type)) return file;

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.8));

        return blob && blob.size < file.size ? new File([blob], file.name.replace(/\.\w+$/, '.jpg'), { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    }
}

export function registerAdmission(Alpine) {
    Alpine.data('documentUpload', (type) => ({
        busy: false,
        failed: false,

        async pick(event) {
            const file = event.target.files[0];
            if (! file) return;
            this.busy = true;
            this.failed = false;
            this.$wire.upload(`uploads.${type}`, await shrink(file), () => { this.busy = false; }, () => { this.busy = false; this.failed = true; });
        },
    }));
}
