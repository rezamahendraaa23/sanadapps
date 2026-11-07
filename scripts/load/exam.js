// Uji beban ujian serentak (UAT 6, PRD Sistem §11) untuk staging.
// Siapkan data: php artisan sanad:load-prepare --students=200
// Jalankan:     k6 run -e BASE=https://staging.contoh.sch.id -e DATA=storage/app/load.json scripts/load/exam.js
// Headroom 5x:  tambahkan -e VUS=1000 setelah menyiapkan --students=1000.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

const data = JSON.parse(open(__ENV.DATA || '../../storage/app/load.json'));
const attempts = new SharedArray('attempts', () => data.attempts);
const vus = Math.min(Number(__ENV.VUS || attempts.length), attempts.length);

export const options = {
    scenarios: {
        ujian: { executor: 'per-vu-iterations', vus, iterations: 1, maxDuration: '20m' },
    },
    thresholds: {
        'http_req_duration{name:autosave}': ['p(95)<300'],
        'checks': ['rate>0.999'],
    },
};

export default function () {
    const attempt = attempts[(__VU - 1) % attempts.length];
    const params = {
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': attempt.csrf,
            Cookie: `${data.session_cookie}=${encodeURIComponent(attempt.cookie)}`,
        },
        tags: { name: 'autosave' },
    };

    // Seperti HP santri: satu jawaban per 5 sampai 15 detik, autosave tiap jawaban.
    data.questions.forEach((questionId, index) => {
        const res = http.post(`${__ENV.BASE}/ujian/${attempt.uuid}/jawaban`, JSON.stringify({
            token: attempt.token,
            batch: [{ question_id: questionId, answer: 'B', client_seq: index + 1 }],
        }), params);
        check(res, { 'autosave 200': (r) => r.status === 200 });
        sleep(5 + Math.random() * 10);
    });

    const done = http.post(`${__ENV.BASE}/ujian/${attempt.uuid}/kumpulkan`, JSON.stringify({ token: attempt.token }), { ...params, tags: { name: 'submit' } });
    check(done, { 'kumpulkan 200': (r) => r.status === 200 });
}
