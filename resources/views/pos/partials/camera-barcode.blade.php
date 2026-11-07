{{-- Tombol kamera; hanya tampil di browser yang punya BarcodeDetector. Reader USB tetap bisa langsung mengetik ke isian. --}}
<div x-data="qrScanner(['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e'])" x-show="supported" class="flex flex-col gap-2" x-on:qr-found="$wire.set(@js($target), $event.detail){{ isset($then) ? ".then(() => \$wire.{$then}())" : '' }}">
    <video x-ref="video" x-show="running" muted playsinline class="w-full rounded-control bg-bg" aria-label="Kamera untuk memindai barcode"></video>
    <x-ui.button variant="secondary" icon="camera" x-on:click="toggle()"><span x-text="running ? 'Matikan Kamera' : 'Pindai dengan Kamera'">Pindai dengan Kamera</span></x-ui.button>
    <p x-show="problem" x-text="problem" role="alert" class="text-sm text-danger"></p>
</div>
