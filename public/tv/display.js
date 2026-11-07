/* Layar TV. JavaScript sengaja konservatif agar jalan di WebView Android TV lama: tanpa modul, tanpa kelas. */
(function () {
    'use strict';

    var config = window.SANAD_DISPLAY;
    var root = document.getElementById('layar');
    var KEY_TOKEN = 'sanad-display-token';
    var KEY_MANIFEST = 'sanad-display-manifest';
    var KEY_OFFSET = 'sanad-display-offset';
    var KEY_SYNC = 'sanad-display-synced';
    var APP_VERSION = 'web-1';
    var POLL_MS = 8000;
    var HEARTBEAT_MS = 60000;
    var DAYS = ['Ahad', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    var MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var OBLIGATORY = ['fajr', 'dhuhr', 'asr', 'maghrib', 'isha'];
    var LIST = ['imsak', 'fajr', 'sunrise', 'dhuha', 'dhuhr', 'asr', 'maghrib', 'isha'];

    var manifest = null;
    var offset = 0;
    var lastSync = 0;
    var online = false;
    var testUntil = 0;
    var dimmedByCommand = false;
    var slide = { index: -1, until: 0, id: null };
    var renderedKey = '';
    var pairing = null;

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function escapeHtml(text) {
        return String(text === null || text === undefined ? '' : text).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function store(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* penyimpanan penuh: tetap jalan dari memori */ } }
    function read(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
    function clear(key) { try { localStorage.removeItem(key); } catch (e) { /* tidak ada yang perlu dibersihkan */ } }

    function request(method, path, body, done) {
        var xhr = new XMLHttpRequest();
        xhr.open(method, config.api + path);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('Content-Type', 'application/json');
        var token = read(KEY_TOKEN);
        if (token) { xhr.setRequestHeader('Authorization', 'Bearer ' + token); }
        xhr.timeout = 15000;
        xhr.onload = function () {
            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) { /* jawaban bukan JSON */ }
            done(xhr.status, data);
        };
        xhr.onerror = xhr.ontimeout = function () { done(0, null); };
        xhr.send(body ? JSON.stringify(body) : null);
    }

    /* Jam: jam perangkat ditambah selisih terhadap server. Jam perangkat mentah tidak dipercaya. */
    function nowMs() { return Date.now() + offset; }
    function localNow() {
        var d = new Date(nowMs() + (manifest ? manifest.tz_offset_minutes : 420) * 60000);
        return {
            date: d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()),
            minutes: d.getUTCHours() * 60 + d.getUTCMinutes(),
            seconds: d.getUTCSeconds(),
            weekday: d.getUTCDay(),
            label: d.getUTCDate() + ' ' + MONTHS[d.getUTCMonth()] + ' ' + d.getUTCFullYear(),
            hours: d.getUTCHours(),
            minute: d.getUTCMinutes()
        };
    }
    function toMinutes(text) { var p = String(text).split(':'); return parseInt(p[0], 10) * 60 + parseInt(p[1], 10); }
    function dayRow(date) {
        for (var i = 0; i < manifest.prayers.length; i++) { if (manifest.prayers[i].date === date) { return manifest.prayers[i]; } }
        return null;
    }
    function isRamadan(date) { return manifest.ramadan && date >= manifest.ramadan.start && date <= manifest.ramadan.end; }
    function iqamahFor(prayer, date, weekday) {
        var s = manifest.iqamah[prayer] || { minutes: 10, duration: 15 };
        var minutes = s.minutes;
        if (prayer === 'dhuhr' && weekday === 5 && s.friday !== null && s.friday !== undefined) { minutes = s.friday; }
        if (isRamadan(date) && s.ramadan !== null && s.ramadan !== undefined) { minutes = s.ramadan; }
        return { minutes: minutes, duration: s.duration || 15 };
    }
    function prayerLabel(prayer, weekday) {
        if (prayer === 'dhuhr' && weekday === 5 && manifest.friday_mode) { return 'Sholat Jumat'; }
        return manifest.names[prayer];
    }

    /* Mode dihitung dari jadwal tersimpan, tanpa server (PRD Masjid 8.5). */
    function currentMode(t, row) {
        if (!row) { return { name: 'normal' }; }
        var secondsNow = t.minutes * 60 + t.seconds;
        for (var i = 0; i < OBLIGATORY.length; i++) {
            var prayer = OBLIGATORY[i];
            var adhan = toMinutes(row[prayer]) * 60;
            var iq = iqamahFor(prayer, t.date, t.weekday);
            var iqamah = adhan + iq.minutes * 60;
            var end = iqamah + iq.duration * 60;
            var lead = (manifest.countdown_minutes || 15) * 60;
            if (secondsNow >= adhan - lead && secondsNow < adhan) { return { name: 'before_adhan', prayer: prayer, left: adhan - secondsNow }; }
            if (secondsNow >= adhan && secondsNow < adhan + 60) { return { name: 'adhan', prayer: prayer }; }
            if (secondsNow >= adhan + 60 && secondsNow < iqamah) { return { name: 'before_iqamah', prayer: prayer, left: iqamah - secondsNow }; }
            if (secondsNow >= iqamah && secondsNow < end) { return { name: 'praying', prayer: prayer }; }
        }
        return { name: 'normal' };
    }
    function nextPrayer(t, row) {
        var i;
        if (row) {
            for (i = 0; i < OBLIGATORY.length; i++) {
                if (toMinutes(row[OBLIGATORY[i]]) > t.minutes) { return { prayer: OBLIGATORY[i], left: toMinutes(row[OBLIGATORY[i]]) * 60 - (t.minutes * 60 + t.seconds) }; }
            }
        }
        for (i = 0; i < manifest.prayers.length; i++) {
            if (manifest.prayers[i].date > t.date) { return { prayer: 'fajr', left: (24 * 60 - t.minutes) * 60 - t.seconds + toMinutes(manifest.prayers[i].fajr) * 60 }; }
        }
        return null;
    }
    function clockLeft(seconds) {
        var h = Math.floor(seconds / 3600);
        var m = Math.floor(seconds % 3600 / 60);
        var s = seconds % 60;
        return (h > 0 ? h + ':' + pad(m) : m) + ':' + pad(s);
    }

    /* Konten yang boleh tayang saat ini, urut prioritas. Yang lewat waktu hilang sendiri tanpa server. */
    function activeContents(t, row) {
        var now = nowMs();
        return manifest.contents.filter(function (item) {
            if (item.ticker) { return false; }
            if (item.starts_at && item.starts_at > now) { return false; }
            if (item.ends_at && item.ends_at <= now) { return false; }
            var rule = item.rule || {};
            if (rule.days && rule.days.length && rule.days.indexOf(t.weekday === 0 ? 7 : t.weekday) < 0) { return false; }
            if (rule.before_prayer && row) {
                var at = toMinutes(row[rule.before_prayer]);
                if (t.minutes < at - 60 || t.minutes >= at) { return false; }
            }
            return true;
        });
    }
    function tickerText() {
        var now = nowMs();
        return manifest.contents.filter(function (item) {
            return item.ticker && (!item.starts_at || item.starts_at <= now) && (!item.ends_at || item.ends_at > now);
        }).map(function (item) { return item.body && item.body.text ? item.body.text : item.title; }).join('   •   ');
    }
    function autoCards(t) {
        var cards = [];
        var i;
        var today = manifest.kajian.filter(function (k) { return k.day === (t.weekday === 0 ? 7 : t.weekday); });
        if (today.length) {
            var lines = [];
            for (i = 0; i < today.length; i++) { lines.push(today[i].time.replace(':', '.') + '  ' + today[i].title + (today[i].speaker ? ' (' + today[i].speaker + ')' : '')); }
            cards.push({ id: 'kajian', type: 'text', title: 'Kajian hari ini', body: { text: lines.join('\n') }, duration: 12 });
        }
        if (manifest.khutbah.length) {
            var k = manifest.khutbah[0];
            var parts = [k.date.split('-').reverse().join('-')];
            if (k.khatib) { parts.push('Khatib: ' + k.khatib); }
            if (k.imam) { parts.push('Imam: ' + k.imam); }
            if (k.theme) { parts.push('Tema: ' + k.theme); }
            cards.push({ id: 'khutbah', type: 'text', title: 'Jumat berikutnya', body: { text: parts.join('\n') }, duration: 12 });
        }
        if (manifest.duties && manifest.duties.length) {
            var duties = [];
            for (i = 0; i < manifest.duties.length; i++) { duties.push(manifest.duties[i].name + (manifest.duties[i].person ? ': ' + manifest.duties[i].person : '')); }
            cards.push({ id: 'piket', type: 'text', title: 'Piket hari ini', body: { text: duties.join('\n') }, duration: 12 });
        }
        return cards;
    }

    function contentHtml(item) {
        var body = item.body || {};
        if (item.type === 'poster' || item.type === 'qr_donation') {
            return { media: true, html: '<img src="' + escapeHtml(item.media) + '" alt="' + escapeHtml(item.title) + '">' };
        }
        if (item.type === 'video') {
            return { media: true, html: '<video src="' + escapeHtml(item.media) + '" autoplay muted playsinline></video>' };
        }
        if (item.type === 'quote') {
            return { media: false, html: '<div class="arab" lang="ar">' + escapeHtml(body.arabic) + '</div><p>' + escapeHtml(body.text) + '</p><div class="ref">' + escapeHtml(body.source) + '</div>' };
        }
        return { media: false, html: '<h2>' + escapeHtml(item.title) + '</h2><p>' + escapeHtml(body.text) + '</p>' };
    }

    /* Bergilir per slide, atau daftar maksimal 3 item bersamaan. */
    function slotHtml(t, row) {
        var items = activeContents(t, row).concat(autoCards(t));
        if (!items.length) { return { key: 'empty', cls: '', html: '<p class="empty">Selamat datang.</p>' }; }

        if (manifest.screen.pattern === 'list') {
            var list = items.slice(0, 3);
            var html = '';
            for (var i = 0; i < list.length; i++) { html += '<div class="item"><h2>' + escapeHtml(list[i].title) + '</h2><p>' + escapeHtml(list[i].body ? list[i].body.text : '') + '</p></div>'; }
            return { key: 'list:' + list.map(function (x) { return x.id; }).join(','), cls: ' list', html: html };
        }

        var now = Date.now();
        if (slide.index < 0 || now >= slide.until || slide.index >= items.length || !items[slide.index] || items[slide.index].id !== slide.id) {
            var keep = slide.index >= 0 && slide.index < items.length && items[slide.index].id === slide.id && now < slide.until;
            if (!keep) {
                slide.index = (slide.index + 1) % items.length;
                slide.until = now + (items[slide.index].duration || 12) * 1000;
                slide.id = items[slide.index].id;
            }
        }
        var current = contentHtml(items[slide.index]);
        return { key: 'slide:' + slide.id, cls: current.media ? ' media' : '', html: current.html };
    }

    function header(t, row) {
        var hijri = row ? row.hijri : '';
        return '<div class="head"><div>' + escapeHtml(manifest.identity.name) + '</div><div class="date">' + DAYS[t.weekday] + ', ' + t.label + (hijri ? '<br>' + escapeHtml(hijri) : '') + '</div></div>';
    }
    function timesHtml(row, next, t) {
        var html = '<div class="times">';
        for (var i = 0; i < LIST.length; i++) {
            var key = LIST[i];
            if (key === 'dhuha' || (key === 'imsak' && !isRamadan(t.date) && LIST.length > 7 && false)) { continue; }
            html += '<div class="time' + (next && next.prayer === key ? ' is-next' : '') + '"><b>' + escapeHtml(prayerLabel(key, t.weekday)) + '</b><span>' + (row ? row[key].replace(':', '.') : '-') + '</span></div>';
        }
        return html + '</div>';
    }
    function syncNote() {
        if (online) { return ''; }
        if (!lastSync) { return 'Offline'; }
        var d = new Date(lastSync + (manifest.tz_offset_minutes * 60000));
        return 'Offline. Terakhir sinkron ' + pad(d.getUTCHours()) + '.' + pad(d.getUTCMinutes());
    }
    function footer() {
        var text = tickerText();
        return '<div class="foot"><div class="ticker">' + escapeHtml(text) + '</div><div class="sync" id="sync">' + escapeHtml(syncNote()) + '</div></div>';
    }

    function paint(key, cls, html) {
        if (key !== renderedKey) {
            root.className = cls;
            root.innerHTML = html;
            renderedKey = key;
        }
    }
    function setText(id, text) { var el = document.getElementById(id); if (el && el.textContent !== text) { el.textContent = text; } }

    function renderPairing() {
        if (!pairing) { paint('pair-wait', 'pair', '<div class="full"><h1>Menyiapkan layar…</h1></div>'); return; }
        paint('pair:' + pairing.code, 'pair', '<div class="full"><h1>Pasangkan layar ini</h1><div class="code">' + escapeHtml(pairing.code) + '</div><div class="sub">Masukkan kode ini di panel SANAD, menu Layar</div><div class="sub" style="font-size:3.2rem">' + escapeHtml(config.pairHint) + '</div></div>');
    }

    function render() {
        if (!manifest) { renderPairing(); return; }
        var t = localNow();
        var row = dayRow(t.date);
        var night = !row || t.minutes >= toMinutes(row.maghrib) || t.minutes < toMinutes(row.sunrise);
        var theme = night ? 'night' : 'day';
        var clock = pad(t.hours) + '.' + pad(t.minute);
        var seconds = pad(t.seconds);
        // Tanda hidup untuk aplikasi pembungkus Android TV.
        document.title = 'Layar ' + clock;

        if (Date.now() < testUntil) {
            paint('test', 'test', '<div class="full"><div>Layar tes: ' + escapeHtml(manifest.screen.name) + '</div></div>');
            return;
        }

        if (manifest.emergency && (!manifest.emergency.ends_at || manifest.emergency.ends_at > nowMs())) {
            paint('emergency:' + manifest.emergency.id + manifest.emergency.text, 'emergency', '<div class="full"><h1>Pengumuman Penting</h1><div class="sub">' + escapeHtml(manifest.emergency.text) + '</div></div>');
            return;
        }

        var mode = manifest.screen.profile === 'announcement_only' ? { name: 'normal' } : currentMode(t, row);

        if (mode.name === 'praying') {
            paint('praying:' + mode.prayer, 'praying', '<div class="full"><div class="clock" id="clock">' + clock + '</div><p>Sholat sedang berlangsung</p></div>');
            setText('clock', clock);
            return;
        }
        if (mode.name === 'adhan') {
            paint('adhan:' + mode.prayer, theme, '<div class="full"><h1>Waktu ' + escapeHtml(prayerLabel(mode.prayer, t.weekday)) + '</h1><div class="big" id="clock">' + clock + '</div></div>');
            setText('clock', clock);
            return;
        }
        if (mode.name === 'before_adhan' || mode.name === 'before_iqamah') {
            var isAdhan = mode.name === 'before_adhan';
            var note = '';
            if (mode.prayer === 'dhuhr' && t.weekday === 5 && manifest.friday_mode && manifest.khutbah.length && manifest.khutbah[0].date === t.date) {
                var fr = manifest.khutbah[0];
                note = (fr.khatib ? 'Khatib: ' + fr.khatib + '  ' : '') + (fr.imam ? 'Imam: ' + fr.imam : '');
            }
            paint(mode.name + mode.prayer, theme, '<div class="full"><h1>' + (isAdhan ? 'Menuju ' : 'Iqamah ') + escapeHtml(prayerLabel(mode.prayer, t.weekday)) + '</h1><div class="big" id="count"></div><div class="sub strong">' + clock + '</div>' + (note ? '<div class="sub">' + escapeHtml(note) + '</div>' : '') + '</div>');
            setText('count', clockLeft(mode.left));
            return;
        }

        var showTimes = manifest.screen.profile !== 'announcement_only';
        var next = showTimes ? nextPrayer(t, row) : null;
        var slot = slotHtml(t, row);
        var note2 = next ? iqamahFor(next.prayer, t.date, t.weekday) : null;
        var ramadanLine = isRamadan(t.date) && row ? 'Imsak ' + row.imsak.replace(':', '.') + ', berbuka ' + row.maghrib.replace(':', '.') : '';
        var key = 'normal:' + theme + ':' + (next ? next.prayer : '') + ':' + slot.key + ':' + t.date + ':' + (row ? row.fajr : '') + ':' + tickerText() + ':' + online;
        var left = showTimes
            ? '<div class="col-left"><div class="clock"><span id="clock">' + clock + '</span><small>.<span id="sec">' + seconds + '</span></small></div><div class="next-label">' + (next ? 'Menuju ' + escapeHtml(prayerLabel(next.prayer, t.weekday)) : '') + '</div><div class="next-count" id="left"></div><div class="next-note">' + (next ? 'Iqamah +' + note2.minutes + ' menit' : '') + (ramadanLine ? '<br>' + ramadanLine : '') + '</div></div>'
            : '';
        var right = showTimes
            ? '<div class="col-right">' + timesHtml(row, next, t) + '<div class="slot' + slot.cls + '">' + slot.html + '</div></div>'
            : '<div class="only"><div class="slot' + slot.cls + '">' + slot.html + '</div></div>';
        paint(key, theme, '<div class="safe nudge" id="safe">' + header(t, row) + '<div class="main">' + left + right + '</div>' + footer() + '</div>');
        setText('clock', clock);
        setText('sec', seconds);
        if (next) { setText('left', clockLeft(next.left)); }
    }

    /* Pergeseran piksel halus dan redup terjadwal (MSJ-DSP-10). */
    var dimEl = null;
    function applyDim(t) {
        var rule = manifest && manifest.screen.brightness;
        var level = dimmedByCommand ? 0.6 : 0;
        if (rule && rule.from && rule.until) {
            var from = toMinutes(rule.from);
            var until = toMinutes(rule.until);
            var inside = from < until ? (t.minutes >= from && t.minutes < until) : (t.minutes >= from || t.minutes < until);
            if (inside) { level = Math.max(level, (rule.level || 40) / 100); }
        }
        if (!dimEl) {
            dimEl = document.createElement('div');
            dimEl.className = 'dim';
            document.body.appendChild(dimEl);
        }
        dimEl.style.opacity = String(level);
    }
    function nudge() {
        var safe = document.getElementById('safe');
        if (safe) { safe.style.transform = 'translate(' + (Math.floor(Math.random() * 7) - 3) + 'px,' + (Math.floor(Math.random() * 7) - 3) + 'px)'; }
    }

    function applyManifest(data) {
        manifest = data;
        store(KEY_MANIFEST, JSON.stringify(data));
        adoptServerTime(data.server_time);
        lastSync = Date.now();
        store(KEY_SYNC, String(lastSync));
        renderedKey = '';
        cacheMedia(data);
    }
    function adoptServerTime(serverTime) {
        if (!serverTime) { return; }
        offset = serverTime - Date.now();
        store(KEY_OFFSET, String(offset));
    }
    function cacheMedia(data) {
        if (!navigator.serviceWorker || !navigator.serviceWorker.controller) { return; }
        var urls = data.contents.filter(function (c) { return c.media; }).map(function (c) { return c.media; });
        navigator.serviceWorker.controller.postMessage({ type: 'cache-media', urls: urls });
    }

    function runCommand(command) {
        request('POST', '/command-ack', { id: command.id }, function () {
            if (command.command === 'reload') { location.reload(); }
        });
        if (command.command === 'test') { testUntil = Date.now() + 15000; renderedKey = ''; }
        if (command.command === 'dim') { dimmedByCommand = true; }
        if (command.command === 'undim') { dimmedByCommand = false; }
    }

    function backToPairing() {
        clear(KEY_TOKEN);
        clear(KEY_MANIFEST);
        manifest = null;
        renderedKey = '';
        startPairing();
    }

    function poll() {
        if (!read(KEY_TOKEN)) { return; }
        request('GET', '/manifest/version', null, function (status, data) {
            if (status === 401) { backToPairing(); return; }
            if (status !== 200 || !data) {
                if (online) { online = false; renderedKey = ''; }
                return;
            }
            if (!online) { online = true; renderedKey = ''; }
            adoptServerTime(data.server_time);
            lastSync = Date.now();
            store(KEY_SYNC, String(lastSync));
            for (var i = 0; i < data.commands.length; i++) { runCommand(data.commands[i]); }
            if (!manifest || data.version !== manifest.version) {
                request('GET', '/manifest', null, function (s, full) { if (s === 200 && full) { applyManifest(full); } });
            }
        });
    }
    function heartbeat() {
        if (!read(KEY_TOKEN)) { return; }
        request('POST', '/heartbeat', { version: manifest ? manifest.version : null, app_version: APP_VERSION, clock_offset_ms: Math.round(offset) }, function () {});
    }

    function startPairing() {
        pairing = null;
        request('POST', '/pair/start', null, function (status, data) {
            if (status === 200 && data) { pairing = data; renderedKey = ''; } else { setTimeout(startPairing, 5000); }
        });
    }
    function pollPairing() {
        if (!pairing || read(KEY_TOKEN)) { return; }
        request('GET', '/pair/status?pairing_token=' + encodeURIComponent(pairing.pairing_token), null, function (status, data) {
            if (status !== 200 || !data) { return; }
            if (data.status === 'claimed') {
                store(KEY_TOKEN, data.token);
                online = true;
                pairing = null;
                poll();
                heartbeat();
            } else if (data.status === 'expired') {
                startPairing();
            }
        });
    }

    function boot() {
        if (config.preview) {
            manifest = config.preview;
            adoptServerTime(manifest.server_time);
            online = true;
            setInterval(function () { render(); }, 1000);
            render();
            return;
        }

        var saved = read(KEY_MANIFEST);
        if (saved) { try { manifest = JSON.parse(saved); } catch (e) { manifest = null; } }
        offset = parseInt(read(KEY_OFFSET) || '0', 10) || 0;
        lastSync = parseInt(read(KEY_SYNC) || '0', 10) || 0;

        if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/display-sw.js').catch(function () {}); }

        if (read(KEY_TOKEN)) { online = navigator.onLine !== false; poll(); heartbeat(); } else { startPairing(); }

        setInterval(function () { try { render(); if (manifest) { applyDim(localNow()); } } catch (e) { if (window.console) { console.error(e); } } }, 1000);
        setInterval(poll, POLL_MS);
        setInterval(pollPairing, 3000);
        setInterval(heartbeat, HEARTBEAT_MS);
        setInterval(nudge, 300000);
        render();
    }

    boot();
}());
