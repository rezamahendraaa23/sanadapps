{{-- Deretan logo sertifikat: pesantren, yayasan, atau mitra, paling banyak empat. --}}
@if ($logos !== [])
    <div class="logos {{ $align ?? '' }}">
        @foreach ($logos as $logoSrc)
            <img src="{{ $logoSrc }}" alt="">
        @endforeach
    </div>
@endif
