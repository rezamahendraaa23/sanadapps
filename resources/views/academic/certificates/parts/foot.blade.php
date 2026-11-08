<div class="meta">Nomor {{ $result->certificate_no ?? '-' }}<br>NIS {{ $nis }}</div>
<div class="sign">
    <div>{{ $issued }}</div>
    <div>{{ $signerTitle }}</div>
    <div class="who">{{ $signerName ?: ' ' }}</div>
</div>
