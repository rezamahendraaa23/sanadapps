<x-layouts.base :title="$attempt->exam->title">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div x-data="examRunner(@js($config))" class="flex min-h-dvh flex-col bg-bg">
        <header class="sticky top-0 z-20 border-b border-border bg-surface pt-[env(safe-area-inset-top)]">
            <div class="mx-auto flex min-h-14 w-full max-w-[720px] items-center gap-2 px-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $attempt->exam->title }}</p>
                    <p class="flex items-center gap-1 truncate text-xs text-text-muted" aria-live="polite">
                        <span x-show="!online" class="inline-flex"><x-ui.icon name="wifi-off" size="sm" /></span>
                        <span x-text="saveLabel"></span>
                    </p>
                </div>
                <p class="font-display font-tabular shrink-0 text-xl font-semibold" role="timer" aria-label="Sisa waktu" x-text="timer"></p>
                <button type="button" class="inline-flex size-11 shrink-0 items-center justify-center rounded-control border border-border" aria-label="Daftar nomor soal" x-on:click="sheet = true"><x-ui.icon name="grid-3x3" /></button>
            </div>
        </header>

        <div class="mx-auto flex w-full max-w-[720px] flex-1 flex-col gap-3 p-3 pb-28">
            <div x-show="warning" x-cloak role="alert" class="flex items-center gap-2 rounded-control bg-warning-soft p-3 text-base font-semibold"><x-ui.icon name="clock-alert" /><span x-text="warning"></span></div>
            <div x-show="!online" x-cloak class="flex items-center gap-2 rounded-control bg-warning-soft p-3 text-sm"><x-ui.icon name="wifi-off" size="sm" />Koneksi terputus. Lanjutkan mengerjakan, jawaban tersimpan di HP.</div>
            <div x-show="pendingSubmit && !online" x-cloak class="rounded-control bg-info-soft p-3 text-sm">Ujian dikumpulkan. Jawaban terkirim otomatis begitu tersambung.</div>

            <article class="rounded-card border border-border bg-surface p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-2 text-sm text-text-muted">
                    <span>Soal <span class="font-tabular font-semibold text-text" x-text="index + 1"></span> dari <span x-text="questions.length"></span></span>
                    <span class="font-tabular" x-text="current.points + ' poin'"></span>
                </div>
                <div :dir="current.direction" :lang="current.direction === 'rtl' ? 'ar' : 'id'" :class="current.direction === 'rtl' ? 'font-arabic text-[1.5rem] leading-[2]' : 'text-lg leading-relaxed'" class="[&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:list-disc [&_ul]:pl-6" x-html="current.body"></div>
                <template x-if="current.media">
                    <img :src="current.media" alt="Gambar soal" loading="lazy" class="mt-3 aspect-video w-full rounded-control bg-bg object-contain">
                </template>

                <template x-if="current.type === 'mcq' || current.type === 'true_false'">
                    <div role="radiogroup" aria-label="Pilihan jawaban" class="mt-4 flex flex-col gap-2">
                        <template x-for="option in current.options" :key="option.key">
                            <button type="button" role="radio" :aria-checked="isChosen(current, option.key).toString()" x-on:click="setAnswer(current, option.key)"
                                :class="isChosen(current, option.key) ? 'border-primary bg-primary-soft font-semibold' : 'border-border bg-surface'"
                                class="flex min-h-12 w-full items-center gap-3 rounded-control border-2 p-3 text-left">
                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full border-2" :class="isChosen(current, option.key) ? 'border-primary' : 'border-text-muted'"><span class="size-3 rounded-full bg-primary" x-show="isChosen(current, option.key)"></span></span>
                                <span class="min-w-0 flex-1 break-words" dir="auto" :class="optionClass(option)" x-text="option.text"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <template x-if="current.type === 'multi'">
                    <div role="group" aria-label="Pilih semua yang benar" class="mt-4 flex flex-col gap-2">
                        <p class="text-sm text-text-muted">Boleh lebih dari satu.</p>
                        <template x-for="option in current.options" :key="option.key">
                            <button type="button" role="checkbox" :aria-checked="isChosen(current, option.key).toString()" x-on:click="toggleMulti(current, option.key)"
                                :class="isChosen(current, option.key) ? 'border-primary bg-primary-soft font-semibold' : 'border-border bg-surface'"
                                class="flex min-h-12 w-full items-center gap-3 rounded-control border-2 p-3 text-left">
                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-control border-2" :class="isChosen(current, option.key) ? 'border-primary bg-primary text-on-primary' : 'border-text-muted'"><span x-show="isChosen(current, option.key)"><x-ui.icon name="check" size="sm" /></span></span>
                                <span class="min-w-0 flex-1 break-words" dir="auto" :class="optionClass(option)" x-text="option.text"></span>
                            </button>
                        </template>
                    </div>
                </template>

                <template x-if="current.type === 'short'">
                    <div class="mt-4">
                        <label for="jawaban-singkat" class="text-sm font-semibold">Jawaban</label>
                        <input id="jawaban-singkat" type="text" autocomplete="off" :dir="current.direction" :value="answers[current.id] ?? ''" x-on:input="setAnswer(current, $event.target.value)" class="mt-1 min-h-12 w-full rounded-control border border-text-muted bg-surface px-3 text-base">
                    </div>
                </template>

                <template x-if="current.type === 'essay'">
                    <div class="mt-4">
                        <label for="jawaban-esai" class="text-sm font-semibold">Jawaban</label>
                        <textarea id="jawaban-esai" rows="6" :dir="current.direction" :value="answers[current.id] ?? ''" x-on:input="setAnswer(current, $event.target.value); $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'" class="mt-1 w-full rounded-control border border-text-muted bg-surface p-3 text-base"></textarea>
                        <p class="text-right text-xs text-text-muted"><span class="font-tabular" x-text="(answers[current.id] ?? '').length"></span> karakter</p>
                    </div>
                </template>
            </article>
        </div>

        <nav aria-label="Navigasi soal" class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)]">
            <div class="mx-auto grid w-full max-w-[720px] grid-cols-3 gap-2 p-2">
                <button type="button" class="min-h-12 rounded-control border border-primary font-semibold text-primary disabled:opacity-40" :disabled="index === 0" x-on:click="go(index - 1)">Sebelumnya</button>
                <button type="button" class="inline-flex min-h-12 items-center justify-center gap-1 rounded-control border font-semibold" :class="doubts[current.id] ? 'border-warning bg-warning-soft' : 'border-border'" :aria-pressed="(!!doubts[current.id]).toString()" x-on:click="toggleDoubt()"><x-ui.icon name="flag" size="sm" />Ragu</button>
                <button type="button" class="min-h-12 rounded-control bg-primary font-semibold text-on-primary" x-on:click="index === questions.length - 1 ? review = true : go(index + 1)" x-text="index === questions.length - 1 ? 'Tinjau' : 'Berikutnya'"></button>
            </div>
        </nav>

        <div x-show="sheet" x-cloak class="fixed inset-0 z-30 flex items-end justify-center bg-scrim md:items-center" x-on:click.self="sheet = false" x-on:keydown.escape.window="sheet = false">
            <div role="dialog" aria-modal="true" aria-label="Daftar nomor soal" x-trap.noscroll="sheet" class="max-h-[85dvh] w-full overflow-y-auto rounded-t-modal bg-surface p-4 md:max-w-lg md:rounded-b-modal">
                <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-semibold">Nomor soal</h2><button type="button" class="inline-flex size-11 items-center justify-center" aria-label="Tutup" x-on:click="sheet = false"><x-ui.icon name="x" /></button></div>
                <div class="grid grid-cols-6 gap-2">
                    <template x-for="(question, position) in questions" :key="question.id">
                        <button type="button" x-on:click="go(position)" :aria-label="'Soal ' + (position + 1) + ', ' + statusOf(question)"
                            :class="{ 'border-primary bg-primary text-on-primary': statusOf(question) === 'terjawab', 'border-warning bg-warning-soft': statusOf(question) === 'ragu', 'border-border': statusOf(question) === 'belum', 'outline-2 outline-offset-2 outline-primary': position === index }"
                            class="font-tabular flex min-h-11 items-center justify-center rounded-control border-2 font-semibold" x-text="position + 1"></button>
                    </template>
                </div>
                <dl class="mt-3 flex flex-wrap gap-4 text-sm">
                    <div class="flex items-center gap-1"><span class="inline-flex size-4 rounded-sm bg-primary"></span><dt>Terjawab</dt></div>
                    <div class="flex items-center gap-1"><span class="inline-flex size-4 rounded-sm border-2 border-warning bg-warning-soft"></span><dt>Ragu</dt></div>
                    <div class="flex items-center gap-1"><span class="inline-flex size-4 rounded-sm border-2 border-border"></span><dt>Belum</dt></div>
                </dl>
                <button type="button" class="mt-4 min-h-12 w-full rounded-control bg-primary font-semibold text-on-primary" x-on:click="sheet = false; review = true">Tinjau dan Kumpulkan</button>
            </div>
        </div>

        <div x-show="review" x-cloak class="fixed inset-0 z-30 flex items-end justify-center bg-scrim md:items-center" x-on:keydown.escape.window="review = false">
            <div role="alertdialog" aria-modal="true" aria-label="Kumpulkan ujian" x-trap.noscroll="review" class="max-h-[85dvh] w-full overflow-y-auto rounded-t-modal bg-surface p-4 md:max-w-lg md:rounded-b-modal">
                <h2 class="text-lg font-semibold">Tinjau jawaban</h2>
                <dl class="mt-2 grid grid-cols-3 gap-2 text-center">
                    <div><dd class="font-display font-tabular text-2xl font-semibold" x-text="answeredCount"></dd><dt class="text-sm">Terjawab</dt></div>
                    <div><dd class="font-display font-tabular text-2xl font-semibold" x-text="questions.length - answeredCount"></dd><dt class="text-sm">Belum</dt></div>
                    <div><dd class="font-display font-tabular text-2xl font-semibold" x-text="doubtCount"></dd><dt class="text-sm">Ragu</dt></div>
                </dl>
                <div x-show="unansweredNumbers.length" class="mt-3">
                    <p class="text-sm font-semibold">Belum dijawab:</p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <template x-for="number in unansweredNumbers" :key="number">
                            <button type="button" class="font-tabular inline-flex size-11 items-center justify-center rounded-control border border-border" x-on:click="go(number - 1)" x-text="number"></button>
                        </template>
                    </div>
                </div>
                <p class="mt-3 text-base font-semibold" x-text="unansweredNumbers.length ? 'Kumpulkan sekarang? ' + unansweredNumbers.length + ' soal belum dijawab.' : 'Kumpulkan sekarang?'"></p>
                <div class="mt-3 flex flex-col-reverse gap-2 md:flex-row md:justify-end">
                    <button type="button" class="min-h-12 rounded-control border border-primary px-4 font-semibold text-primary" x-on:click="review = false">Kembali Periksa</button>
                    <button type="button" class="min-h-12 rounded-control bg-primary px-4 font-semibold text-on-primary disabled:opacity-40" :disabled="submitting" x-on:click="collect()">Kumpulkan</button>
                </div>
            </div>
        </div>

        <div x-show="blurred && !takenOver" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-scrim p-4" x-on:keydown.escape.window="blurred = false">
            <div role="alertdialog" aria-modal="true" aria-label="Tetap di halaman ujian" x-trap="blurred && !takenOver" class="w-full max-w-sm rounded-modal bg-surface p-4">
                <h2 class="text-lg font-semibold">Tetap di halaman ujian</h2>
                <p class="mt-1 text-base">Kepindahan dari halaman ini tercatat dan terlihat oleh ustadz.</p>
                <button type="button" class="mt-3 min-h-12 w-full rounded-control bg-primary font-semibold text-on-primary" x-on:click="blurred = false">Lanjut Mengerjakan</button>
            </div>
        </div>

        <div x-show="takenOver" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-surface p-6 text-center">
            <div><x-ui.icon name="smartphone" size="xl" class="mx-auto text-primary" /><h2 class="font-display mt-3 text-2xl font-semibold">Ujian dilanjutkan di perangkat lain</h2><p class="mt-1 text-base">Halaman ini tidak lagi menyimpan jawaban.</p></div>
        </div>

        <div x-show="toast" x-cloak role="status" class="fixed top-16 left-1/2 z-40 -translate-x-1/2 rounded-full border border-success bg-success-soft px-4 py-2 text-sm font-semibold" x-text="toast"></div>
    </div>
</x-layouts.base>
