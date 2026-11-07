// Kamera absensi: selfie santri (S-10), pos wajah, dan pendaftaran wajah (U-15).
const DB_NAME = 'sanad-selfie';

function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore('queue');
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function queueRead(key) {
    try {
        const db = await openDb();
        return await new Promise((resolve) => {
            const get = db.transaction('queue').objectStore('queue').get(key);
            get.onsuccess = () => resolve(get.result ?? null);
            get.onerror = () => resolve(null);
        });
    } catch {
        return null;
    }
}

async function queueWrite(key, value) {
    const db = await openDb();
    // Baru dianggap aman setelah transaksinya selesai, karena layarnya menjanjikan foto tersimpan.
    await new Promise((resolve, reject) => {
        const transaction = db.transaction('queue', 'readwrite');
        transaction.objectStore('queue').put(value, key);
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
}

async function queueClear(key) {
    try {
        const db = await openDb();
        db.transaction('queue', 'readwrite').objectStore('queue').delete(key);
    } catch {
        // Tidak ada yang perlu dibersihkan.
    }
}

async function openCamera(video) {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 960 } }, audio: false });
    video.srcObject = stream;
    await new Promise((resolve) => { if (video.videoWidth) resolve(); else video.onloadedmetadata = resolve; });
    return stream;
}

function grab(video, stamp = null) {
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');
    context.drawImage(video, 0, 0);

    if (stamp) {
        const size = Math.round(canvas.height / 12);
        context.font = `700 ${size}px sans-serif`;
        const width = context.measureText(stamp).width;
        context.fillStyle = 'rgba(0, 0, 0, 0.7)';
        context.fillRect(0, canvas.height - size * 1.6, width + size, size * 1.6);
        context.fillStyle = 'white';
        context.fillText(stamp, size / 2, canvas.height - size * 0.45);
    }

    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
}

function headers() {
    return { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content };
}

export function registerAttendanceCamera(Alpine) {
    Alpine.data('selfieCamera', (config) => ({
        config,
        state: 'starting',
        error: '',
        review: false,
        statusLabel: '',
        blob: null,
        fileMode: false,
        coords: null,
        location: 'checking',
        stream: null,

        get locationLabel() {
            return { ok: 'Lokasi terbaca', denied: 'Lokasi tidak tersedia, absen akan ditinjau', checking: 'Membaca lokasi…' }[this.location];
        },

        async init() {
            window.addEventListener('online', () => this.flushQueue());
            window.addEventListener('pagehide', () => this.stream?.getTracks().forEach((track) => track.stop()));

            if (await this.flushQueue()) return;
            if (config.geofence) this.locate();
            await this.startCamera();
        },

        async startCamera() {
            this.state = 'starting';
            try {
                this.stream = await openCamera(this.$refs.video);
                this.state = 'live';
            } catch {
                this.state = 'denied';
            }
        },

        locate() {
            if (! navigator.geolocation) { this.location = 'denied'; return; }
            navigator.geolocation.getCurrentPosition(
                (position) => { this.coords = { lat: position.coords.latitude, lng: position.coords.longitude }; this.location = 'ok'; },
                () => { this.location = 'denied'; },
                { enableHighAccuracy: true, timeout: 10000 },
            );
        },

        async shoot() {
            this.error = '';
            this.blob = await grab(this.$refs.video, `Kode ${config.code}`);
            this.fileMode = false;
            this.$refs.preview.src = URL.createObjectURL(this.blob);
            this.state = 'preview';
        },

        retake() {
            this.blob = null;
            this.error = '';
            this.state = this.fileMode ? 'denied' : 'live';
        },

        fromFile(event) {
            const file = event.target.files[0];
            if (! file) return;
            this.blob = file;
            this.fileMode = true;
            this.$refs.preview.src = URL.createObjectURL(file);
            this.state = 'preview';
        },

        form(entry) {
            const data = new FormData();
            data.append('photo', entry.blob, 'selfie.jpg');
            if (entry.coords) { data.append('lat', entry.coords.lat); data.append('lng', entry.coords.lng); }
            if (entry.fileMode) data.append('from_file', '1');
            if (entry.delayed) data.append('delayed', '1');
            return data;
        },

        async post(entry) {
            const response = await fetch(config.url, { method: 'POST', headers: headers(), credentials: 'same-origin', body: this.form(entry) });
            const data = await response.json().catch(() => ({}));
            return { response, data };
        },

        finish(data) {
            this.review = !! data.review;
            this.statusLabel = data.status ?? '';
            this.state = 'done';
            this.stream?.getTracks().forEach((track) => track.stop());
        },

        async send() {
            // Lokasi disalin: objek reaktif Alpine tidak bisa disimpan di IndexedDB.
            const entry = { blob: this.blob, coords: this.coords ? { lat: this.coords.lat, lng: this.coords.lng } : null, fileMode: this.fileMode };
            this.state = 'sending';
            this.error = '';

            try {
                const { response, data } = await this.post(entry);

                if (response.ok) { this.finish(data); return; }

                this.error = data.message ?? 'Foto belum bisa dikirim. Coba lagi.';
                this.state = 'preview';
            } catch {
                try {
                    await queueWrite(config.session, { ...entry, delayed: true });
                    this.state = 'queued';
                } catch {
                    this.error = 'Tidak tersambung dan penyimpanan HP penuh. Coba lagi saat tersambung.';
                    this.state = 'preview';
                }
            }
        },

        async flushQueue() {
            const entry = await queueRead(config.session);
            if (! entry) return false;
            this.state = 'queued';
            if (! navigator.onLine) return true;

            try {
                const { response, data } = await this.post(entry);
                await queueClear(config.session);

                if (response.ok) {
                    this.finish(data);
                } else {
                    this.error = data.message ?? 'Foto tersimpan tidak bisa dikirim. Ambil ulang.';
                    await this.startCamera();
                }
            } catch {
                // Masih putus: tetap antre sampai peristiwa online berikutnya.
            }

            return true;
        },
    }));

    Alpine.data('faceKiosk', (config) => ({
        ready: false,
        denied: false,
        busy: false,
        challenge: '',
        result: null,

        async init() {
            try {
                const stream = await openCamera(this.$refs.video);
                window.addEventListener('pagehide', () => stream.getTracks().forEach((track) => track.stop()));
                this.ready = true;
            } catch {
                this.denied = true;
            }
        },

        async scan() {
            this.busy = true;
            this.result = null;

            try {
                const started = await fetch(config.challengeUrl, { method: 'POST', headers: headers(), credentials: 'same-origin' });
                const { token, challenge } = await started.json();
                this.challenge = challenge;
                await new Promise((resolve) => setTimeout(resolve, 1500));
                const first = await grab(this.$refs.video);
                await new Promise((resolve) => setTimeout(resolve, 1000));
                const second = await grab(this.$refs.video);
                const data = new FormData();
                data.append('photo', first, 'wajah.jpg');
                data.append('liveness_frame', second, 'gerak.jpg');
                data.append('liveness_token', token);
                const response = await fetch(config.scanUrl, { method: 'POST', headers: headers(), credentials: 'same-origin', body: data });
                this.result = await response.json();
            } catch {
                this.result = { ok: false, name: '', message: 'Tidak tersambung. Pakai kartu atau manual.' };
            }

            this.challenge = '';
            this.busy = false;
        },
    }));

    Alpine.data('faceEnroll', (needed) => ({
        needed,
        shots: [],
        ready: false,
        busy: false,
        error: '',

        async init() {
            try {
                const stream = await openCamera(this.$refs.video);
                this.stream = stream;
                this.ready = true;
                document.addEventListener('livewire:navigating', () => stream.getTracks().forEach((track) => track.stop()), { once: true });
            } catch {
                this.error = 'Kamera tidak bisa dibuka.';
            }
        },

        async shoot() {
            this.shots.push(await grab(this.$refs.video));
        },

        send() {
            this.busy = true;
            const files = this.shots.map((blob, index) => new File([blob], `foto-${index + 1}.jpg`, { type: 'image/jpeg' }));
            this.$wire.uploadMultiple('photos', files, () => {
                this.$wire.enroll().then(() => { this.shots = []; this.busy = false; });
            }, () => { this.busy = false; this.error = 'Foto gagal terkirim.'; });
        },
    }));
}
