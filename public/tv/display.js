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

    /* Mode dihitung dari jadwal tersimpan, tanpa server. Setelah adzan langsung hitung mundur iqamah,
       lalu layar gelap selama sholat (keputusan pemilik). */
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
            if (secondsNow >= adhan - lead && secondsNow < adhan) { return { name: 'before_adhan', prayer: prayer, left: adhan - secondsNow, total: lead }; }
            if (secondsNow >= adhan && secondsNow < iqamah) { return { name: 'before_iqamah', prayer: prayer, left: iqamah - secondsNow, total: iqamah - adhan }; }
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
    /* Kartu otomatis: selalu ada sesuatu yang berguna walau pesantren tidak membuat pengumuman. */
    function autoCards(t, row) {
        var cards = [];
        var i;
        var weekday = t.weekday === 0 ? 7 : t.weekday;
        var today = manifest.kajian.filter(function (k) { return k.day === weekday; });
        if (today.length) {
            var lines = [];
            for (i = 0; i < today.length; i++) { lines.push(today[i].time.replace(':', '.') + '  ' + today[i].title + (today[i].speaker ? ', ' + today[i].speaker : '') + (today[i].location ? ' di ' + today[i].location : '')); }
            cards.push({ id: 'kajian', type: 'agenda', kind: 'Kajian hari ini', title: today.length === 1 ? today[0].title : today.length + ' kajian hari ini', body: { text: lines.join('\n') }, duration: 12 });
        }
        if (manifest.khutbah.length) {
            var k = manifest.khutbah[0];
            var parts = [];
            if (k.khatib) { parts.push('Khatib: ' + k.khatib); }
            if (k.imam) { parts.push('Imam: ' + k.imam); }
            if (k.theme) { parts.push('Tema: ' + k.theme); }
            cards.push({ id: 'khutbah', type: 'text', kind: k.date === t.date ? 'Jumat ini' : 'Jumat berikutnya', title: k.date.split('-').reverse().join('.'), body: { text: parts.join('\n') }, duration: 12 });
        }
        if (manifest.duties && manifest.duties.length) {
            var duties = [];
            for (i = 0; i < manifest.duties.length; i++) { duties.push(manifest.duties[i].name + (manifest.duties[i].person ? ': ' + manifest.duties[i].person : '')); }
            cards.push({ id: 'piket', type: 'text', kind: 'Piket hari ini', title: 'Yang bertugas', body: { text: duties.join('\n') }, duration: 12 });
        }
        var quotes = manifest.quotes || [];
        if (quotes.length) {
            // Satu ayat atau hadis per jam, berganti urut agar tidak berulang terus.
            var q = quotes[(t.hours + new Date(nowMs()).getUTCDate() * 24) % quotes.length];
            cards.push({ id: q.id, type: 'quote', body: { arabic: q.arabic, text: q.text, source: q.source }, duration: 15 });
        }
        if (row) {
            cards.push({ id: 'today', type: 'today', duration: 10 });
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
        if (item.type === 'today') {
            var t = localNow();
            var row = dayRow(t.date);
            var next = nextPrayer(t, row);
            var fri = (5 - t.weekday + 7) % 7;
            return { media: false, html: '<div class="kind"><i aria-hidden="true"></i>Hari ini</div><h2>' + DAYS[t.weekday] + ', ' + escapeHtml(row ? row.hijri : '') + '</h2><p>' + (next ? 'Berikutnya ' + escapeHtml(prayerLabel(next.prayer, t.weekday)) + ' pukul ' + (row && row[next.prayer] ? row[next.prayer].replace(':', '.') : '') + '.' : '') + (fri === 0 ? '\nHari ini Jumat. Perbanyak sholawat.' : '\n' + fri + ' hari lagi menuju Jumat.') + '</p>' };
        }
        if (item.type === 'quote') {
            return { media: false, html: '<div class="kind"><i aria-hidden="true"></i>Renungan</div><div class="arab" lang="ar">' + escapeHtml(body.arabic) + '</div><p>' + escapeHtml(body.text) + '</p><div class="ref">' + escapeHtml(body.source) + '</div>' };
        }
        var kinds = { text: 'Pengumuman', agenda: 'Agenda', obituary: 'Kabar duka' };
        var kind = item.kind || kinds[item.type];
        return { media: false, html: (kind ? '<div class="kind"><i aria-hidden="true"></i>' + escapeHtml(kind) + '</div>' : '') + '<h2>' + escapeHtml(item.title) + '</h2><p>' + escapeHtml(body.text) + '</p>' };
    }

    /* Bergilir per slide, atau daftar maksimal 3 item bersamaan. */
    function slotHtml(t, row) {
        var items = activeContents(t, row).concat(autoCards(t, row));
        if (!items.length) { return { key: 'empty', cls: '', html: '<p class="empty">Selamat datang di ' + escapeHtml(manifest.identity.name) + '.</p>' }; }

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

    /* Ikon garis satu keluarga untuk tiap waktu: bulan, fajar, terbit, matahari, sore, terbenam, malam. */
    var ICON = {
        imsak: '<path d="M19 14.6A7.6 7.6 0 1 1 9.4 5a6 6 0 0 0 9.6 9.6z"/><path d="M17 2.5l.7 1.6 1.6.7-1.6.7-.7 1.6-.7-1.6-1.6-.7 1.6-.7z"/>',
        fajr: '<path d="M2.5 18h19M8 21h8"/><path d="M7 18a5 5 0 0 1 10 0"/><path d="M12 6v3M5.6 10.4l1.8 1.8M18.4 10.4l-1.8 1.8"/>',
        sunrise: '<path d="M2.5 19h19"/><path d="M7 19a5 5 0 0 1 10 0"/><path d="M12 3.5v7M9 6.5l3-3 3 3"/>',
        dhuhr: '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
        asr: '<circle cx="9" cy="9" r="3.4"/><path d="M9 2.6v1.4M2.6 9H4M4.5 4.5l1 1M13.5 4.5l-1 1"/><path d="M8.5 19.5h8.8a3.4 3.4 0 0 0 0-6.8 4.9 4.9 0 0 0-9.3 1.4 2.7 2.7 0 0 0 .5 5.4z"/>',
        maghrib: '<path d="M2.5 19h19"/><path d="M7 19a5 5 0 0 1 10 0"/><path d="M12 10.5v-7M9 7.5l3 3 3-3"/>',
        isha: '<path d="M16 15.5A7 7 0 1 1 8.5 6a5.5 5.5 0 0 0 7.5 9.5z"/><path d="M18 4l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6zM20.5 10.5l.4.9.9.4-.9.4-.4.9-.4-.9-.9-.4.9-.4z"/>'
    };
    function icon(key) { return ICON[key] ? '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' + ICON[key] + '</svg>' : ''; }
    /* Bintang delapan (khatam): dua persegi bertumpuk. */
    var KHATAM = '<svg class="khatam" viewBox="-4 -4 108 108" aria-hidden="true"><path d="M50 0l14.6 14.6H85.4V35.4L100 50 85.4 64.6V85.4H64.6L50 100 35.4 85.4H14.6V64.6L0 50 14.6 35.4V14.6H35.4z"/><path transform="translate(50 50) scale(0.66) translate(-50 -50)" d="M50 0l14.6 14.6H85.4V35.4L100 50 85.4 64.6V85.4H64.6L50 100 35.4 85.4H14.6V64.6L0 50 14.6 35.4V14.6H35.4z"/><circle cx="50" cy="50" r="14"/></svg>';

    function header(t, row) {
        var logo = manifest.identity.logo ? '<img src="' + escapeHtml(manifest.identity.logo) + '" alt="">' : '<i class="mark" aria-hidden="true"><svg viewBox="0 0 64 64" fill-rule="evenodd"><path d="M32 4l2.2 4.6L32 12l-2.2-3.4z"/><path d="M32 12c-11 0-19 7.6-19 17v2h38v-2c0-9.4-8-17-19-17z"/><path d="M11 33h42v25H11zM27 58V46a5 5 0 0 1 10 0v12z"/><path d="M2 24h6v34H2zM56 24h6v34h-6z"/><path d="M5 17l3 7H2zM59 17l3 7h-6z"/></svg></i>';
        return '<div class="head"><div class="brand">' + logo + '<div><b>' + escapeHtml(manifest.identity.name) + '</b><span>' + escapeHtml(manifest.identity.school && manifest.identity.school !== manifest.identity.name ? manifest.identity.school : 'Jadwal sholat dan informasi') + '</span></div></div>'
            + '<div class="cal">' + DAYS[t.weekday] + ', ' + t.label + (row ? '<span>' + escapeHtml(row.hijri) + '</span>' : '') + '</div></div>';
    }

    /* Garis waktu: isi batang menunjukkan posisi sekarang di antara Imsak dan Isya. */
    function lineHtml(row, next, t) {
        var keys = ['imsak', 'fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];
        var html = '';
        var first = row ? toMinutes(row.imsak) : 0;
        var last = row ? toMinutes(row.isha) : 1;
        var progress = row ? Math.max(0, Math.min(1, (t.minutes - first) / Math.max(1, last - first))) : 0;
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var past = row && toMinutes(row[key]) <= t.minutes && !(next && next.prayer === key);
            html += '<div class="slotime' + (next && next.prayer === key ? ' is-next' : '') + (past ? ' past' : '') + '">' + icon(key) + '<b>' + escapeHtml(prayerLabel(key, t.weekday)) + '</b><span>' + (row ? row[key].replace(':', '.') : '-') + '</span></div>';
        }
        return '<div class="line"><div class="track"></div><div class="fill" id="fill" style="width:' + (progress * 100).toFixed(2) + '%"></div>' + html + '</div>';
    }

    function syncNote() {
        if (online) { return ''; }
        if (!lastSync) { return 'Offline'; }
        var d = new Date(lastSync + (manifest.tz_offset_minutes * 60000));
        return 'Offline. Terakhir sinkron ' + pad(d.getUTCHours()) + '.' + pad(d.getUTCMinutes());
    }
    function footer() {
        var text = tickerText();
        if (!text && online) { return ''; }
        return '<div class="foot"><div class="ticker">' + escapeHtml(text) + '</div><div class="sync">' + escapeHtml(syncNote()) + '</div></div>';
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
        if (!pairing) { paint('pair-wait', 'pair', '<div class="full"><div class="box"><h1>Menyiapkan layar…</h1></div></div>'); return; }
        paint('pair:' + pairing.code, 'pair', '<div class="full"><div class="box"><h1>Pasangkan layar ini</h1><div class="code">' + escapeHtml(pairing.code) + '</div><p>Masukkan kode ini di panel SANAD, menu Layar TV</p><p>' + escapeHtml(config.pairHint) + '</p></div></div>');
    }

    var RING = 2 * Math.PI * 46;
    function countdownHtml(title, kicker, note) {
        return '<div class="full"><div class="count"><div class="ring"><svg viewBox="0 0 100 100"><circle class="bg" cx="50" cy="50" r="46"></circle><circle class="fg" id="ring" cx="50" cy="50" r="46" stroke-dasharray="' + RING.toFixed(2) + '" stroke-dashoffset="0"></circle></svg><div class="num" id="count"></div>' + KHATAM.replace('khatam', 'khatam ring-star') + '</div>'
            + '<div class="words"><p>' + escapeHtml(kicker) + '</p><h1>' + escapeHtml(title) + '</h1>' + (note ? '<span>' + escapeHtml(note) + '</span>' : '') + '</div></div></div>';
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
            paint('test', 'test', '<div class="full"><div class="box">Layar tes: ' + escapeHtml(manifest.screen.name) + '</div></div>');
            return;
        }

        if (manifest.emergency && (!manifest.emergency.ends_at || manifest.emergency.ends_at > nowMs())) {
            paint('emergency:' + manifest.emergency.id + manifest.emergency.text, 'emergency', '<div class="full"><div class="box"><h1>Pengumuman Penting</h1><p>' + escapeHtml(manifest.emergency.text) + '</p></div></div>');
            return;
        }

        var mode = manifest.screen.profile === 'announcement_only' ? { name: 'normal' } : currentMode(t, row);

        if (mode.name === 'praying') {
            paint('praying', 'praying', '');
            return;
        }
        if (mode.name === 'before_adhan' || mode.name === 'before_iqamah') {
            var isAdhan = mode.name === 'before_adhan';
            var label = prayerLabel(mode.prayer, t.weekday);
            var note = 'Sekarang ' + clock;
            if (mode.prayer === 'dhuhr' && t.weekday === 5 && manifest.friday_mode && manifest.khutbah.length && manifest.khutbah[0].date === t.date) {
                var fr = manifest.khutbah[0];
                note = (fr.khatib ? 'Khatib ' + fr.khatib : '') + (fr.imam ? (fr.khatib ? ', imam ' : 'Imam ') + fr.imam : '');
            }
            paint(mode.name + mode.prayer + theme, theme, countdownHtml(isAdhan ? label : 'Iqamah', isAdhan ? 'Menuju adzan' : 'Adzan ' + label + ' telah berkumandang', isAdhan ? note : 'Rapatkan dan luruskan shaf'));
            setText('count', clockLeft(mode.left));
            var ring = document.getElementById('ring');
            if (ring) { ring.setAttribute('stroke-dashoffset', (RING * (1 - mode.left / mode.total)).toFixed(2)); }
            return;
        }

        var showTimes = manifest.screen.profile !== 'announcement_only';
        var next = showTimes ? nextPrayer(t, row) : null;
        var slot = slotHtml(t, row);
        var iq = next ? iqamahFor(next.prayer, t.date, t.weekday) : null;
        var ramadanLine = isRamadan(t.date) && row ? 'Imsak ' + row.imsak.replace(':', '.') + ', berbuka ' + row.maghrib.replace(':', '.') : '';
        var key = 'normal:' + theme + ':' + (next ? next.prayer : '') + ':' + slot.key + ':' + t.date + ':' + (row ? row.fajr : '') + ':' + tickerText() + ':' + online + ':' + t.minutes;
        var hero = showTimes
            ? '<div class="hero">' + KHATAM + '<div class="clock"><span id="clock">' + clock + '</span><small>.<span id="sec">' + seconds + '</span></small></div>'
                + (next ? '<div class="next">' + icon(next.prayer) + '<b>' + escapeHtml(prayerLabel(next.prayer, t.weekday)) + '</b><span id="left"></span></div><div class="note">Iqamah ' + iq.minutes + ' menit setelah adzan' + (ramadanLine ? '. ' + ramadanLine : '') + '</div>' : '')
                + '</div>'
            : '';
        paint(key, theme, '<div class="safe nudge" id="safe">' + header(t, row) + '<div class="main">' + hero + '<div class="slot' + slot.cls + '">' + slot.html + '</div></div>' + (showTimes ? lineHtml(row, next, t) : '') + footer() + '</div>');
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
