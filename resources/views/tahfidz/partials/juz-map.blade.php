<ul class="grid grid-cols-6 gap-1" aria-label="Peta hafalan per juz">
    @foreach ($juzMap as $juz)
        <li @class(['flex min-h-11 flex-col items-center justify-center rounded-control border text-xs', 'border-primary bg-primary text-on-primary' => $juz['percent'] === 100, 'border-primary bg-primary-soft' => $juz['percent'] > 0 && $juz['percent'] < 100, 'border-border text-text-muted' => $juz['percent'] === 0]) title="Juz {{ $juz['juz'] }}: {{ $juz['percent'] }}%">
            <span class="font-tabular font-semibold">{{ $juz['juz'] }}</span>
            @if ($juz['percent'] === 100)<x-ui.icon name="check" size="sm" />@elseif ($juz['percent'] > 0)<span class="font-tabular">{{ $juz['percent'] }}%</span>@endif
        </li>
    @endforeach
</ul>
