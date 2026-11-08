{{-- Pemotong gambar: begitu berkas dipilih, bingkai mengikuti ukuran tampil di layar. Geser dan perbesar lalu hasilnya otomatis terunggah. --}}
<div class="flex flex-col gap-2">
<div class="flex flex-col gap-2" wire:ignore x-data="imageCropper()" x-on:open-modal.window="clear()">
    <label for="field-media" class="text-sm font-semibold">Gambar (JPG atau PNG)</label>
    <input id="field-media" x-ref="file" type="file" accept="image/jpeg,image/png" x-on:change="pick($event)" class="min-h-11 w-full text-base">
    <p x-show="problem" x-text="problem" role="alert" class="text-sm text-danger"></p>
    <div x-show="ready" x-cloak class="flex flex-col gap-2">
        <div x-ref="stage" data-testid="crop-stage" class="relative w-full cursor-grab touch-none overflow-hidden rounded-control bg-text active:cursor-grabbing"
            :style="`aspect-ratio: ${width} / ${height}`"
            x-on:pointerdown="start($event)" x-on:pointermove="drag($event)" x-on:pointerup="end()" x-on:pointercancel="end()" x-on:wheel.prevent="wheel($event)">
            <canvas x-ref="view" class="absolute inset-0 size-full"></canvas>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <label for="crop-zoom" class="text-sm font-semibold">Perbesar</label>
            <input id="crop-zoom" type="range" min="1" max="4" step="0.01" x-model.number="zoom" x-on:input="changed()" class="min-h-11 min-w-40 flex-1 accent-primary">
            <button type="button" x-on:click="zoom = 1; x = 0; y = 0; changed()" class="min-h-11 rounded-control px-3 text-sm font-semibold text-primary hover:bg-primary-soft">Atur Ulang</button>
        </div>
        <p class="text-sm text-text-muted" aria-live="polite">
            <span x-text="`Ukuran layar ${width} x ${height}. Geser gambar untuk memilih bagian yang tampil.`"></span>
            <span x-show="uploading" class="font-semibold">Mengunggah…</span>
            <span x-show="!uploading && uploaded" class="font-semibold text-success">Gambar siap.</span>
        </p>
    </div>
</div>
@error('media')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
</div>
