// Kamera absensi: pos wajah dan pendaftaran wajah (U-15).
// Kamera yang baru dilepas halaman atau aplikasi lain sering menolak sesaat (NotReadableError/AbortError).
// Itu dicoba lagi; izin yang ditolak (NotAllowedError) langsung dilaporkan.
const CAMERA_BUSY = ['NotReadableError', 'AbortError', 'TrackStartError'];

async function getCamera(attempt = 0) {
    try {
        return await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 960 } }, audio: false });
    } catch (error) {
        if (attempt >= 3 || !CAMERA_BUSY.includes(error?.name)) throw error;
        await new Promise((resolve) => setTimeout(resolve, 400 * (attempt + 1)));

        return getCamera(attempt + 1);
    }
}

async function openCamera(video) {
    const stream = await getCamera();
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

// Mesin pengenal di browser: wajah menjadi 128 angka, ditambah ukuran buka mata dan arah kepala untuk cek gerakan.
let engineLoading = null;

function loadEngine(libraryUrl = '/vendor/face-api/face-api.js', modelUrl = '/vendor/face-api/model') {
    if (engineLoading) return engineLoading;

    engineLoading = new Promise((resolve, reject) => {
        if (window.faceapi) { resolve(window.faceapi); return; }
        const script = document.createElement('script');
        script.src = libraryUrl;
        script.onload = () => resolve(window.faceapi);
        script.onerror = () => reject(new Error('Mesin pengenal gagal dimuat.'));
        document.head.appendChild(script);
    }).then(async (api) => {
        await api.tf.ready();
        await Promise.all([
            api.nets.tinyFaceDetector.loadFromUri(modelUrl),
            api.nets.faceLandmark68Net.loadFromUri(modelUrl),
            api.nets.faceRecognitionNet.loadFromUri(modelUrl),
        ]);
        // Hasil pertama setelah model dimuat terukur meleset 0,11, jadi model dipanaskan dulu dengan gambar kosong.
        const blank = document.createElement('canvas');
        blank.width = 150;
        blank.height = 150;
        blank.getContext('2d').fillRect(0, 0, 150, 150);
        await api.nets.tinyFaceDetector.locateFaces(blank, new api.TinyFaceDetectorOptions({ inputSize: 320 }));
        await api.nets.faceLandmark68Net.detectLandmarks(blank);
        await api.nets.faceRecognitionNet.computeFaceDescriptor(blank);
        return api;
    }).catch((error) => { engineLoading = null; throw error; });

    return engineLoading;
}

function eyeOpening(eye) {
    const gap = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    return (gap(eye[1], eye[5]) + gap(eye[2], eye[4])) / (2 * gap(eye[0], eye[3]));
}

async function describeFace(input) {
    const api = await loadEngine();
    const found = await api.detectSingleFace(input, new api.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 })).withFaceLandmarks().withFaceDescriptor();

    if (! found) return null;

    const left = found.landmarks.getLeftEye();
    const right = found.landmarks.getRightEye();
    const center = (eye) => eye.reduce((sum, point) => sum + point.x, 0) / eye.length;
    const between = (center(left) + center(right)) / 2;
    const span = Math.abs(center(right) - center(left)) || 1;

    return {
        descriptor: Array.from(found.descriptor).map((value) => Math.round(value * 1e5) / 1e5),
        ear: (eyeOpening(left) + eyeOpening(right)) / 2,
        yaw: (found.landmarks.getNose()[3].x - between) / span,
        width: found.detection.box.width / (input.videoWidth || input.naturalWidth || input.width || 1),
    };
}

window.sanadFace = { load: loadEngine, describe: describeFace };

const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function headers() {
    return { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content };
}

export function registerAttendanceCamera(Alpine) {
    Alpine.data('faceKiosk', (config) => ({
        config,
        ready: false,
        denied: false,
        busy: false,
        challenge: '',
        result: null,
        status: 'Menyiapkan pengenal wajah…',
        running: true,

        async init() {
            try {
                const stream = await openCamera(this.$refs.video);
                window.addEventListener('pagehide', () => stream.getTracks().forEach((track) => track.stop()));
                window.addEventListener('pagehide', () => { this.running = false; });
                this.ready = true;
            } catch {
                this.denied = true;
                return;
            }

            if (config.browser) this.watch();
        },

        // Tanpa tombol: wajah yang muncul langsung diberi tantangan gerakan, lalu dicocokkan.
        async watch() {
            try {
                await loadEngine(config.libraryUrl, config.modelUrl);
            } catch {
                this.status = 'Pengenal wajah gagal dimuat. Pakai kartu atau manual.';
                return;
            }

            while (this.running) {
                this.status = 'Hadapkan wajah ke kamera';
                const face = await describeFace(this.$refs.video).catch(() => null);

                if (! face || face.width < 0.15) { await pause(350); continue; }

                await this.recognize();
                await pause(2500);
                this.result = null;
                this.challenge = '';
            }
        },

        async recognize() {
            try {
                const started = await fetch(config.challengeUrl, { method: 'POST', headers: headers(), credentials: 'same-origin' });
                const { token, challenge } = await started.json();
                this.challenge = challenge;
                this.status = 'Ikuti petunjuk di atas';
                const motion = [];
                let last = null;
                const until = Date.now() + 4000;

                while (Date.now() < until) {
                    const face = await describeFace(this.$refs.video).catch(() => null);
                    if (face) { motion.push({ ear: face.ear, yaw: face.yaw }); last = face; }
                    await pause(60);
                }

                this.challenge = '';

                if (! last) { this.result = { ok: false, name: '', message: 'Wajah hilang dari kamera. Coba lagi.' }; return; }

                this.status = 'Mencocokkan…';
                const data = new FormData();
                data.append('descriptor', JSON.stringify(last.descriptor));
                data.append('motion', JSON.stringify(motion));
                data.append('liveness_token', token);
                data.append('photo', await grab(this.$refs.video), 'wajah.jpg');
                const response = await fetch(config.scanUrl, { method: 'POST', headers: headers(), credentials: 'same-origin', body: data });
                this.result = await response.json();
            } catch {
                this.result = { ok: false, name: '', message: 'Tidak tersambung. Pakai kartu atau manual.' };
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

    Alpine.data('faceEnroll', (config) => ({
        needed: config.needed,
        shots: [],
        ready: false,
        busy: false,
        error: '',
        notice: config.browser ? 'Menyiapkan pengenal wajah...' : '',
        // Scan otomatis (mesin di browser): arahan, wajah sudah pas atau belum, dan arah toleh langkah kedua.
        prompt: '',
        aligned: false,
        scanning: false,
        firstSide: 0,
        steady: 0,
        alive: true,

        async init() {
            let stream;
            try {
                stream = await openCamera(this.$refs.video);
            } catch {
                this.error = 'Kamera tidak bisa dibuka.';
                this.notice = '';
                return;
            }
            const stop = () => { this.alive = false; stream.getTracks().forEach((track) => track.stop()); };
            document.addEventListener('livewire:navigating', stop, { once: true });
            window.addEventListener('pagehide', stop, { once: true });

            if (config.browser) {
                try {
                    await loadEngine(config.libraryUrl, config.modelUrl);
                    this.notice = '';
                } catch {
                    this.notice = 'Pengenal wajah gagal dimuat. Muat ulang halaman.';
                    return;
                }
            }

            this.ready = true;
            if (config.browser) this.scan();
        },

        /**
         * Langkah ke-n: 0 lurus, 1 toleh ke satu sisi, 2 toleh ke sisi lain, selebihnya lurus lagi.
         */
        stepFor(index) {
            return [
                { text: 'Lihat lurus ke kamera', ok: (face) => Math.abs(face.yaw) < 0.12 },
                { text: 'Tolehkan kepala sedikit ke samping', ok: (face) => Math.abs(face.yaw) > 0.18 },
                { text: 'Sekarang toleh ke sisi lainnya', ok: (face) => face.yaw * this.firstSide < 0 && Math.abs(face.yaw) > 0.18 },
            ][index] ?? { text: 'Lihat lurus ke kamera', ok: (face) => Math.abs(face.yaw) < 0.12 };
        },

        async scan() {
            if (this.scanning) return;
            this.scanning = true;
            this.steady = 0;

            while (this.alive && this.scanning && this.shots.length < this.needed) {
                const step = this.stepFor(this.shots.length);
                // Lewat window.sanadFace agar bisa diganti wajah tiruan saat pengujian.
                const face = await window.sanadFace.describe(this.$refs.video).catch(() => null);

                if (! face) {
                    [this.prompt, this.aligned, this.steady] = ['Arahkan wajah ke dalam lingkaran', false, 0];
                } else if (face.width < 0.22) {
                    [this.prompt, this.aligned, this.steady] = ['Dekatkan wajah ke kamera', false, 0];
                } else if (! step.ok(face)) {
                    [this.prompt, this.aligned, this.steady] = [step.text, false, 0];
                } else {
                    // Pose harus bertahan dua kali periksa berturut-turut agar foto tidak buram.
                    this.aligned = true;
                    this.steady++;
                    this.prompt = 'Tahan sebentar...';
                    if (this.steady >= 2) {
                        if (this.shots.length === 1) this.firstSide = Math.sign(face.yaw);
                        this.shots.push(face.descriptor);
                        this.steady = 0;
                        this.prompt = this.shots.length < this.needed ? 'Bagus. ' + this.stepFor(this.shots.length).text : 'Selesai, menyimpan...';
                        await pause(700);
                    }
                }
                await pause(200);
            }

            this.scanning = false;
            if (this.alive && this.shots.length >= this.needed) this.send();
        },

        restart() {
            [this.shots, this.firstSide, this.notice] = [[], 0, ''];
            this.scan();
        },

        async shoot() {
            this.busy = true;

            if (config.browser) {
                const face = await describeFace(this.$refs.video).catch(() => null);
                this.notice = face ? '' : 'Wajah belum terdeteksi. Dekatkan dan cari tempat terang.';
                if (face) this.shots.push(face.descriptor);
            } else {
                this.shots.push(await grab(this.$refs.video));
            }

            this.busy = false;
        },

        send() {
            this.busy = true;
            const done = () => { this.shots = []; this.busy = false; this.prompt = ''; this.aligned = false; };

            if (config.browser) {
                this.$wire.enrollDescriptors(this.shots).then(done);
                return;
            }

            const files = this.shots.map((blob, index) => new File([blob], `foto-${index + 1}.jpg`, { type: 'image/jpeg' }));
            this.$wire.uploadMultiple('photos', files, () => {
                this.$wire.enroll().then(done);
            }, () => { this.busy = false; this.error = 'Foto gagal terkirim.'; });
        },
    }));
}
