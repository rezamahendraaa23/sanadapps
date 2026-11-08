// Simulasi scan otomatis: wajah tiruan berurutan, periksa kapan foto diambil dan apakah tersimpan sendiri.
globalThis.window = globalThis;
globalThis.document = { addEventListener() {}, querySelector: () => ({ content: '' }), createElement: () => ({}) };
window.addEventListener = () => {};
const { registerAttendanceCamera } = await import(new URL('../../resources/js/attendance.js', import.meta.url));
const factories = {};
registerAttendanceCamera({ data: (name, fn) => { factories[name] = fn; } });

const poses = [null, { yaw: 0, width: 0.1 }, { yaw: 0.02, width: 0.3 }, { yaw: 0.01, width: 0.3 }, { yaw: 0.3, width: 0.3 }, { yaw: 0.3, width: 0.3 }, { yaw: 0.25, width: 0.3 }, { yaw: -0.3, width: 0.3 }, { yaw: -0.3, width: 0.3 }];
let call = 0;
const prompts = [];
window.sanadFace.describe = async () => { const p = poses[Math.min(call++, poses.length - 1)]; return p ? { ...p, descriptor: [call] } : null; };

let saved = null;
const c = factories.faceEnroll({ needed: 3, browser: true });
Object.assign(c, { $refs: { video: {} }, $wire: { enrollDescriptors: (d) => { saved = d; return Promise.resolve(); } } });
c.ready = true;
const watcher = setInterval(() => { if (prompts.at(-1) !== c.prompt) prompts.push(c.prompt); }, 20);
await c.scan();
await new Promise((r) => setTimeout(r, 50));
clearInterval(watcher);
console.log('arahan:', prompts.filter(Boolean).join(' -> '));
console.log('tersimpan otomatis:', JSON.stringify(saved), 'sisi pertama:', c.firstSide);
if (!saved || saved.length !== 3) process.exit(1);
