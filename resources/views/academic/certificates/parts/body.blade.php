{{-- Isi berbahasa Indonesia, sama di semua desain. --}}
<div class="body">
<div class="given">diberikan kepada</div>
<div class="name">{{ $student }}</div>
<div class="text">telah menyelesaikan <b>{{ $phase->name }}</b> pada {{ $phase->curriculum->name }}<br>sejak {{ $period }}.</div>
<div class="grade">Predikat {{ $predicate }}, nilai {{ $score }}</div>
</div>
