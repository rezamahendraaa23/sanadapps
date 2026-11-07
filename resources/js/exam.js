// Halaman ujian: jawaban disimpan dulu di HP (IndexedDB), lalu dikirim bertahap ke server.
// Waktu selalu dari server: jam HP hanya dipakai lewat selisihnya.
const DB_NAME = 'sanad-ujian';

function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore('attempts');
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function readState(attempt) {
    try {
        const db = await openDb();
        return await new Promise((resolve) => {
            const get = db.transaction('attempts').objectStore('attempts').get(attempt);
            get.onsuccess = () => resolve(get.result ?? null);
            get.onerror = () => resolve(null);
        });
    } catch {
        return null;
    }
}

async function writeState(attempt, state) {
    try {
        const db = await openDb();
        db.transaction('attempts', 'readwrite').objectStore('attempts').put(JSON.parse(JSON.stringify(state)), attempt);
    } catch {
        // Penyimpanan penuh atau mode privat: jawaban tetap dikirim ke server saat tersambung.
    }
}

async function clearState(attempt) {
    try {
        const db = await openDb();
        db.transaction('attempts', 'readwrite').objectStore('attempts').delete(attempt);
    } catch {
        // Tidak ada yang perlu dibersihkan.
    }
}

export function registerExam(Alpine) {
    Alpine.data('examRunner', (config) => ({
        questions: config.questions,
        index: 0,
        answers: {},
        doubts: {},
        dirty: {},
        seq: config.maxSeq,
        offset: new Date(config.serverTime).getTime() - Date.now(),
        deadline: new Date(config.deadline).getTime(),
        now: Date.now(),
        online: navigator.onLine,
        saving: false,
        savedAt: null,
        sheet: false,
        review: false,
        blurred: false,
        takenOver: false,
        finished: false,
        submitting: false,
        pendingSubmit: false,
        warning: '',
        warned: {},
        toast: '',
        events: [],

        async init() {
            this.answers = { ...config.answers };
            const saved = await readState(config.attempt);

            if (saved) {
                this.answers = { ...this.answers, ...saved.answers };
                this.doubts = saved.doubts ?? {};
                this.dirty = saved.dirty ?? {};
                this.index = Math.min(saved.index ?? 0, this.questions.length - 1);
                this.seq = Math.max(this.seq, saved.seq ?? 0);
                this.pendingSubmit = saved.pendingSubmit ?? false;
            }

            setInterval(() => this.tick(), 1000);
            setInterval(() => this.flush(), 5000);
            window.addEventListener('online', () => this.wentOnline());
            window.addEventListener('offline', () => { this.online = false; this.track('offline'); });
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { this.blurred = true; this.track('blur'); } else { this.track('focus'); }
            });
            window.addEventListener('beforeunload', (event) => {
                if (! this.finished && ! this.takenOver) { event.preventDefault(); event.returnValue = ''; }
            });
            history.pushState(null, '', location.href);
            window.addEventListener('popstate', () => { history.pushState(null, '', location.href); this.sheet = true; });
            this.tick();
            this.flush();
        },

        get current() { return this.questions[this.index]; },
        get remaining() { return Math.max(0, Math.floor((this.deadline - this.serverNow()) / 1000)); },
        get timer() {
            const total = this.remaining;
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const seconds = total % 60;
            const pad = (value) => String(value).padStart(2, '0');
            return hours > 0 ? `${hours}:${pad(minutes)}:${pad(seconds)}` : `${pad(minutes)}:${pad(seconds)}`;
        },
        get saveLabel() {
            if (! this.online) return 'Offline, jawaban aman di HP';
            if (this.saving || Object.keys(this.dirty).length) return 'Menyimpan…';
            return this.savedAt ? `Tersimpan ${this.savedAt}` : 'Siap';
        },
        get answeredCount() { return this.questions.filter((question) => this.isAnswered(question)).length; },
        get unansweredNumbers() { return this.questions.map((question, position) => (this.isAnswered(question) ? null : position + 1)).filter(Boolean); },
        get doubtCount() { return this.questions.filter((question) => this.doubts[question.id]).length; },

        serverNow() { return this.now + this.offset; },
        isAnswered(question) {
            const value = this.answers[question.id];
            return Array.isArray(value) ? value.length > 0 : value !== undefined && value !== null && String(value).trim() !== '';
        },
        statusOf(question) {
            if (this.doubts[question.id]) return 'ragu';
            return this.isAnswered(question) ? 'terjawab' : 'belum';
        },

        tick() {
            this.now = Date.now();
            const left = this.remaining;

            for (const [limit, text] of [[300, 'Sisa 5 menit'], [60, 'Sisa 1 menit']]) {
                if (left <= limit && left > 0 && ! this.warned[limit]) {
                    this.warned[limit] = true;
                    this.warning = text;
                    navigator.vibrate?.(150);
                    setTimeout(() => { this.warning = ''; }, 8000);
                }
            }

            if (left === 0 && ! this.finished && ! this.pendingSubmit) {
                this.collect();
            }
        },

        persist() {
            writeState(config.attempt, { answers: this.answers, doubts: this.doubts, dirty: this.dirty, index: this.index, seq: this.seq, pendingSubmit: this.pendingSubmit });
        },

        setAnswer(question, value) {
            this.answers[question.id] = value;
            this.seq += 1;
            this.dirty[question.id] = this.seq;
            this.persist();
            clearTimeout(this.flushTimer);
            this.flushTimer = setTimeout(() => this.flush(), 800);
        },
        toggleMulti(question, key) {
            const value = Array.isArray(this.answers[question.id]) ? [...this.answers[question.id]] : [];
            const position = value.indexOf(key);
            if (position >= 0) value.splice(position, 1); else value.push(key);
            this.setAnswer(question, value);
        },
        isChosen(question, key) {
            const value = this.answers[question.id];
            return Array.isArray(value) ? value.includes(key) : value === key;
        },
        toggleDoubt() {
            this.doubts[this.current.id] = ! this.doubts[this.current.id];
            this.persist();
        },
        go(position) {
            this.index = Math.max(0, Math.min(position, this.questions.length - 1));
            this.sheet = false;
            this.review = false;
            this.persist();
            window.scrollTo(0, 0);
        },

        request(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                credentials: 'same-origin',
                body: JSON.stringify({ token: config.token, ...body }),
            });
        },

        track(type) {
            this.events.push(type);
            if (navigator.onLine) this.sendEvents();
        },
        async sendEvents() {
            if (! this.events.length) return;
            const events = this.events.splice(0, this.events.length);
            try {
                await this.request(config.urls.events, { events });
            } catch {
                this.events.unshift(...events);
            }
        },

        async flush() {
            if (this.saving || this.takenOver || this.finished) return false;
            this.sendEvents();
            const entries = Object.entries(this.dirty);

            if (! entries.length) {
                if (this.pendingSubmit && navigator.onLine) await this.finish();
                return true;
            }

            this.saving = true;

            try {
                const response = await this.request(config.urls.answers, {
                    batch: entries.map(([id, seq]) => ({ question_id: Number(id), answer: this.answers[id] ?? null, client_seq: seq })),
                });
                const data = await response.json();

                if (response.status === 409) {
                    this.saving = false;
                    if (data.reason === 'taken_over') { this.takenOver = true; } else { this.leave(config.urls.done); }
                    return false;
                }

                if (response.ok) {
                    this.online = true;
                    this.offset = new Date(data.server_time).getTime() - Date.now();
                    this.deadline = new Date(data.deadline_at).getTime();

                    for (const [id, seq] of entries) {
                        if (this.dirty[id] === seq) delete this.dirty[id];
                    }

                    const clock = new Date(this.serverNow());
                    this.savedAt = clock.toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour12: false });
                    this.persist();
                }
            } catch {
                this.online = false;
                this.saving = false;
                return false;
            }

            this.saving = false;

            if (this.pendingSubmit && ! Object.keys(this.dirty).length) await this.finish();

            return true;
        },

        wentOnline() {
            this.online = true;
            this.track('online');
            this.flush().then((ok) => {
                if (ok) { this.toast = 'Tersinkron'; setTimeout(() => { this.toast = ''; }, 3000); }
            });
        },

        collect() {
            this.pendingSubmit = true;
            this.review = false;
            this.persist();
            this.flush();
        },

        async finish() {
            if (this.submitting) return;
            this.submitting = true;

            try {
                const response = await this.request(config.urls.submit, {});
                const data = await response.json();

                if (response.status === 409 && data.reason === 'taken_over') {
                    this.takenOver = true;
                } else if (response.ok || response.status === 409) {
                    this.leave(data.done ?? config.urls.done);
                }
            } catch {
                this.online = false;
            }

            this.submitting = false;
        },

        leave(url) {
            this.finished = true;
            clearState(config.attempt);
            caches?.delete?.(`sanad-ujian-${config.attempt}`);
            location.replace(url);
        },
    }));

    // Pemeriksaan persiapan (S-04).
    Alpine.data('examPrepare', () => ({
        online: navigator.onLine,
        storage: null,
        async init() {
            window.addEventListener('online', () => { this.online = true; });
            window.addEventListener('offline', () => { this.online = false; });
            try {
                await openDb();
                this.storage = true;
            } catch {
                this.storage = false;
            }
            if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
        },
    }));
}
