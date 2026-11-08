// Pemotong gambar tanpa pustaka: gambar selalu menutup bingkai, hasilnya JPEG seukuran tampil di TV.
export function registerImageCropper(Alpine) {
    Alpine.data('imageCropper', () => ({
        ready: false,
        uploading: false,
        uploaded: false,
        problem: '',
        zoom: 1,
        x: 0,
        y: 0,
        image: null,
        dragging: null,
        timer: null,

        // Ukuran hasil mengikuti pilihan: layar penuh 16:9, bagian pengumuman 8:5.
        get width() {
            return this.$wire.layout === 'full' ? 1920 : 1280;
        },
        get height() {
            return this.$wire.layout === 'full' ? 1080 : 800;
        },

        init() {
            this.$wire.$watch('layout', () => this.changed());
        },

        clear() {
            clearTimeout(this.timer);
            this.image = null;
            this.ready = false;
            this.uploaded = false;
            this.problem = '';
            this.$refs.file.value = '';
        },

        pick(event) {
            const file = event.target.files[0];
            this.problem = '';
            if (!file) return;
            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                this.problem = 'Pilih gambar JPG atau PNG.';
                return;
            }
            const url = URL.createObjectURL(file);
            const image = new Image();
            image.onload = () => {
                this.image = image;
                this.zoom = 1;
                this.x = 0;
                this.y = 0;
                this.ready = true;
                this.$nextTick(() => this.changed());
            };
            image.onerror = () => {
                this.problem = 'Gambar tidak bisa dibuka.';
            };
            image.src = url;
        },

        // Skala terkecil agar gambar menutup bingkai, dikali perbesaran pengguna.
        scale() {
            return Math.max(this.width / this.image.width, this.height / this.image.height) * this.zoom;
        },

        // Geseran dibatasi supaya tidak ada tepi kosong.
        clamp() {
            const scale = this.scale();
            const limitX = Math.max(0, (this.image.width * scale - this.width) / 2);
            const limitY = Math.max(0, (this.image.height * scale - this.height) / 2);
            this.x = Math.min(limitX, Math.max(-limitX, this.x));
            this.y = Math.min(limitY, Math.max(-limitY, this.y));
        },

        draw(canvas) {
            canvas.width = this.width;
            canvas.height = this.height;
            const scale = this.scale();
            const drawWidth = this.image.width * scale;
            const drawHeight = this.image.height * scale;
            const context = canvas.getContext('2d');
            context.imageSmoothingQuality = 'high';
            context.drawImage(this.image, (this.width - drawWidth) / 2 + this.x, (this.height - drawHeight) / 2 + this.y, drawWidth, drawHeight);
        },

        changed() {
            if (!this.image) return;
            this.clamp();
            this.draw(this.$refs.view);
            this.uploaded = false;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.send(), 400);
        },

        start(event) {
            if (!this.image) return;
            this.$refs.stage.setPointerCapture(event.pointerId);
            this.dragging = { x: event.clientX, y: event.clientY, startX: this.x, startY: this.y };
        },
        drag(event) {
            if (!this.dragging) return;
            // Jarak di layar diubah ke piksel bingkai asli.
            const ratio = this.width / this.$refs.stage.clientWidth;
            this.x = this.dragging.startX + (event.clientX - this.dragging.x) * ratio;
            this.y = this.dragging.startY + (event.clientY - this.dragging.y) * ratio;
            this.clamp();
            this.draw(this.$refs.view);
        },
        end() {
            if (!this.dragging) return;
            this.dragging = null;
            this.changed();
        },
        wheel(event) {
            if (!this.image) return;
            this.zoom = Math.min(4, Math.max(1, this.zoom - event.deltaY * 0.001));
            this.changed();
        },

        send() {
            this.$refs.view.toBlob((blob) => {
                if (!blob) {
                    this.problem = 'Gambar gagal diproses. Coba gambar lain.';
                    return;
                }
                this.uploading = true;
                this.$wire.upload('media', new File([blob], 'konten.jpg', { type: 'image/jpeg' }), () => {
                    this.uploading = false;
                    this.uploaded = true;
                }, () => {
                    this.uploading = false;
                    this.problem = 'Unggahan gagal. Coba lagi.';
                });
            }, 'image/jpeg', 0.9);
        },
    }));
}
