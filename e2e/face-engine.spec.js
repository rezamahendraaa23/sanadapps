import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Mesin pengenal berjalan di browser sungguhan, memakai foto contoh dari paket face-api. Foto ini tidak ikut dikirim ke produksi.
const SAMPLES = 'node_modules/@vladmandic/face-api/demo';

test('mesin wajah: model lokal termuat, wajah menjadi 128 angka, foto yang sama berjarak nol', async ({ page }) => {
    test.setTimeout(180_000);
    await page.route('**/__contoh/*.jpg', (route) => route.fulfill({ contentType: 'image/jpeg', body: readFileSync(`${SAMPLES}/${route.request().url().split('/').pop()}`) }));
    const external = [];
    page.on('request', (request) => { if (! request.url().startsWith('http://127.0.0.1') && ! request.url().startsWith('data:') && ! request.url().startsWith('blob:')) external.push(request.url()); });

    await page.goto('/masuk');
    const result = await page.evaluate(async () => {
        await window.sanadFace.load();
        const describe = async (name) => {
            const image = new Image();
            image.src = `/__contoh/${name}.jpg`;
            await image.decode();
            return window.sanadFace.describe(image);
        };
        const names = ['sample1', 'sample2', 'sample3', 'sample4', 'sample5', 'sample6'];
        const faces = {};
        for (const name of names) faces[name] = await describe(name);
        const distance = (a, b) => Math.sqrt(a.reduce((sum, value, index) => sum + (value - b[index]) ** 2, 0));
        const pairs = {};
        for (let i = 0; i < names.length; i++) {
            for (let j = i + 1; j < names.length; j++) {
                if (faces[names[i]] && faces[names[j]]) pairs[`${names[i]}-${names[j]}`] = Number(distance(faces[names[i]].descriptor, faces[names[j]].descriptor).toFixed(3));
            }
        }
        const firstFound = names.find((name) => faces[name]);
        const again = await describe(firstFound);
        const third = await describe(firstFound);
        const fourth = await describe(firstFound);
        const blank = document.createElement('canvas');
        blank.width = 320;
        blank.height = 320;
        return {
            detected: names.filter((name) => faces[name]).length,
            length: faces[firstFound]?.descriptor.length,
            ear: faces[firstFound]?.ear,
            yaw: faces[firstFound]?.yaw,
            backend: window.faceapi.tf.getBackend(),
            firstToSecond: distance(faces[firstFound].descriptor, again.descriptor),
            secondToThird: distance(again.descriptor, third.descriptor),
            same: distance(third.descriptor, fourth.descriptor),
            blank: await window.sanadFace.describe(blank),
            pairs,
        };
    });

    console.log('Jarak antar foto contoh:', JSON.stringify(result.pairs), 'terdeteksi', result.detected, 'backend', result.backend, 'pertama-kedua', result.firstToSecond, 'kedua-ketiga', result.secondToThird, 'ketiga-keempat', result.same);
    expect(result.length).toBe(128);
    expect(result.firstToSecond).toBeLessThan(0.01);
    expect(result.same).toBeLessThan(0.01);
    for (const value of Object.values(result.pairs)) expect(value).toBeGreaterThan(0.48);
    expect(result.blank).toBeNull();
    expect(result.ear).toBeGreaterThan(0);
    expect(external).toEqual([]);
});
