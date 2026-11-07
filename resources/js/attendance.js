// Kamera absensi: pos wajah dan pendaftaran wajah (U-15).
async function openCamera(video) {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 960 } }, audio: false });
    video.srcObject = stream;
    await new Promise((resolve) => { if (video.videoWidth) resolve(); else video.onloadedmetadata = resolve; });
    return stream;
}

function grab(video) {
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
}

function headers() {
    return { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content };
}

export function registerAttendanceCamera(Alpine) {
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
