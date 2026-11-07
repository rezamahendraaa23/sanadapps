// Uji beban kasir kartu (UAT 5 dan 14) untuk staging: beberapa kasir menempelkan kartu bersamaan,
// sebagian dikirim ulang dengan kunci yang sama untuk meniru tap ganda.
// Siapkan data: php artisan sanad:load-prepare --students=200
// Jalankan:     k6 run -e BASE=https://staging.contoh.sch.id -e DATA=storage/app/load.json scripts/load/pos.js
// Setelah uji:  php artisan tinker --execute="dump(app(App\Modules\Wallet\Actions\ReconcileWallets::class)->handle())" harus []
import http from 'k6/http';
import { check } from 'k6';
import { SharedArray } from 'k6/data';

const data = JSON.parse(open(__ENV.DATA || '../../storage/app/load.json'));
const cards = new SharedArray('cards', () => data.cards);

export const options = {
    scenarios: {
        kasir: { executor: 'constant-arrival-rate', rate: Number(__ENV.RATE || 20), timeUnit: '1s', duration: __ENV.DURATION || '2m', preAllocatedVUs: 20, maxVUs: 100 },
    },
    thresholds: {
        'http_req_duration{name:charge}': ['p(95)<300'],
        'checks': ['rate>0.999'],
    },
};

export default function () {
    const card = cards[Math.floor(Math.random() * cards.length)];
    const key = `${__VU}-${__ITER}`;
    const body = JSON.stringify({ card_uid: card, amount: 2000, merchant_id: data.merchant, idempotency_key: key });
    const params = { headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${data.device_key}` }, tags: { name: 'charge' } };

    const first = http.post(`${__ENV.BASE}/api/v1/device/wallet/charge`, body, params);
    check(first, { 'belanja berhasil': (r) => r.status === 200 && r.json('ok') === true });

    // Satu dari sepuluh transaksi ditempel ulang: hasilnya harus sama dan saldo tidak terpotong lagi.
    if (__ITER % 10 === 0) {
        const again = http.post(`${__ENV.BASE}/api/v1/device/wallet/charge`, body, params);
        check(again, { 'tap ganda tidak memotong lagi': (r) => r.status === 200 && r.json('balance') === first.json('balance') });
    }
}
