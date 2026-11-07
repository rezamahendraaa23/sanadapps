<div class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-ui.banner tone="warning">Kolom di berkas ini kolom umum data santri, belum dicocokkan dengan template resmi Dapodik atau EMIS. Periksa dulu sebelum diunggah ke sistem tujuan.</x-ui.banner>
    <x-ui.card title="Unduh berkas">
        <div class="flex flex-col gap-3">
            <x-ui.select label="Untuk" name="kind" :options="['dapodik' => 'Dapodik', 'emis' => 'EMIS']" wire:model.live="kind" />
            <x-ui.select label="Kelas" name="classroom" placeholder="Semua kelas" :options="$classrooms" wire:model.live="classroom" />
            <x-ui.select label="Status santri" name="status" :options="['active' => 'Aktif', 'graduated' => 'Lulus', 'all' => 'Semua']" wire:model.live="status" />
            <x-ui.button icon="file-down" class="self-start" :href="route('staff.export.download', ['untuk' => $kind, 'kelas' => $classroom, 'status' => $status])">Unduh Excel</x-ui.button>
        </div>
    </x-ui.card>
</div>
