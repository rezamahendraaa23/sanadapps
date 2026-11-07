@php
    $student = auth()->user()->student?->load('classroom', 'dormitory');
    $announcements = \App\Modules\Core\Models\Announcement::query()->publishedFor(array_filter([$student]))->limit(5)->get();
@endphp

<x-layouts.portal title="Beranda" role="santri">
    <x-ui.card>
        <p class="text-lg font-semibold">Assalamu'alaikum, {{ $student?->name ?? auth()->user()->name }}</p>
        @if ($student)
            <p class="text-sm text-text-muted">NIS {{ $student->nis }}{{ $student->classroom ? ' · '.$student->classroom->name : '' }}{{ $student->dormitory ? ' · '.$student->dormitory->name : '' }}</p>
        @else
            <p class="text-sm text-text-muted">Akun ini belum terhubung ke data santri. Hubungi admin pesantren.</p>
        @endif
    </x-ui.card>

    @if ($student && app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('academic'))
        @php($time = now(config('sanad.display_timezone'))->format('H:i:s'))
        @php($todaySchedule = \App\Modules\Academic\Support\StudentSchedule::forStudent($student, \App\Modules\Academic\Support\StudentSchedule::todayIso()))
        @php($current = $todaySchedule->first(fn ($schedule) => (string) $schedule->end_time > $time))
        @if ($current)
            <x-ui.hero-card :label="(string) $current->start_time <= $time ? 'Sekarang' : 'Berikutnya'" :value="$current->startLabel()">
                {{ $current->label() }}{{ $current->room ? ', '.$current->room : '' }}.
            </x-ui.hero-card>
        @endif
    @endif

    @if ($student && app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('face_attendance'))
        <p class="flex items-center gap-2 rounded-card border border-border bg-surface p-4 text-sm">
            <x-ui.icon name="scan-face" size="sm" />
            Data wajah: {{ \App\Modules\Academic\Models\FaceEnrollment::query()->where('student_id', $student->id)->where('status', 'active')->exists() ? 'Terdaftar' : 'Belum terdaftar' }}. Hanya wali yang bisa menyetujui atau mencabut.
        </p>
    @endif

    @if ($student && app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('exam'))
        @php($openExams = \App\Modules\Exam\Models\Exam::query()->forStudent($student)->where('status', 'published')->where('start_at', '<=', now())->where('end_at', '>', now())->orderBy('end_at')->get())
        @if ($openExams->isNotEmpty())
            <x-ui.card title="Ujian tersedia">
                @foreach ($openExams as $openExam)
                    <div class="flex items-center justify-between gap-3 border-b border-border py-2 last:border-b-0">
                        <span class="min-w-0 text-base break-words">{{ $openExam->title }}</span>
                        <x-ui.button class="shrink-0" :href="route('santri.exams.prepare', $openExam)" wire:navigate>Mulai<span class="sr-only"> {{ $openExam->title }}</span></x-ui.button>
                    </div>
                @endforeach
            </x-ui.card>
        @endif
    @endif

    @if ($student && app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('wallet'))
        <x-ui.card>
            <p class="text-sm text-text-muted">Saldo</p>
            <p class="font-display font-tabular text-2xl font-semibold">{{ \App\Modules\Finance\Support\Rupiah::format(\App\Modules\Wallet\Models\Wallet::forStudent($student)->balance) }}</p>
        </x-ui.card>
    @endif

    @if ($student && app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('leave'))
        <a href="{{ route('santri.leaves') }}" wire:navigate class="flex min-h-11 items-center justify-between gap-2 rounded-card border border-border bg-surface p-4">
            <span class="flex min-w-0 items-center gap-3 text-base font-semibold"><x-ui.icon name="door-open" />Izin saya</span>
            <x-ui.icon name="chevron-right" class="shrink-0" />
        </a>
    @endif

    @include('core.partials.announcements', ['announcements' => $announcements])
</x-layouts.portal>
