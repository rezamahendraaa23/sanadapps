@php($canManage = auth()->user()->can('create', \App\Modules\Core\Models\Student::class))

<div>
    <div class="mb-4 flex flex-col gap-3" x-data="{ filters: false, actions: false }">
        <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end md:justify-between">
            <div class="grid w-full gap-3 md:grid-cols-4 lg:max-w-4xl">
                <x-ui.input label="Cari nama atau NIS" name="search" type="search" wire:model.live.debounce.300ms="search" />
                <div class="hidden md:contents" :class="filters && '!contents'">
                    <x-ui.select label="Kelas" name="classroom" placeholder="Semua kelas" :options="$classrooms" wire:model.live="classroom" />
                    <x-ui.select label="Asrama" name="dormitory" placeholder="Semua asrama" :options="$dormitories" wire:model.live="dormitory" />
                    <x-ui.select label="Status" name="status" :options="['active' => 'Aktif', 'inactive' => 'Nonaktif', 'graduated' => 'Lulus', 'dropped' => 'Keluar', 'all' => 'Semua']" wire:model.live="status" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 md:hidden">
                <x-ui.button variant="secondary" icon="list-filter" x-on:click="filters = !filters" x-bind:aria-expanded="filters.toString()">Filter</x-ui.button>
                @if ($canManage)
                    <x-ui.button variant="secondary" x-on:click="actions = !actions" x-bind:aria-expanded="actions.toString()">Aksi Lain</x-ui.button>
                @endif
            </div>
            @if ($canManage)
                <div class="flex flex-col gap-2 md:flex-row md:flex-wrap">
                    <div class="hidden flex-col gap-2 md:contents" :class="actions && '!flex md:!contents'">
                        @can('masters.manage')
                            <x-ui.button variant="secondary" :href="route('staff.students.promotion')" wire:navigate>Kenaikan Kelas</x-ui.button>
                            <x-ui.button variant="secondary" :href="route('staff.students.custom-fields')" wire:navigate>Field Tambahan</x-ui.button>
                        @endcan
                        <x-ui.button variant="secondary" icon="file-down" :href="route('staff.students.export')">Ekspor</x-ui.button>
                        <x-ui.button variant="secondary" icon="upload" :href="route('staff.students.import')" wire:navigate>Impor</x-ui.button>
                    </div>
                    <x-ui.button icon="plus" :href="route('staff.students.create')" wire:navigate>Tambah Santri</x-ui.button>
                </div>
            @endif
        </div>
    </div>

    <x-ui.card>
        @if ($students->isEmpty())
            @if ($hasAnyStudent)
                <x-ui.empty-state icon="search" message="Tidak ada santri yang cocok dengan filter ini.">
                    <x-slot:action><x-ui.button variant="secondary" wire:click="clearFilters">Hapus filter</x-ui.button></x-slot:action>
                </x-ui.empty-state>
            @elseif ($canManage)
                <x-ui.empty-state icon="users" message="Belum ada santri. Impor dari Excel atau tambahkan satu per satu.">
                    <x-slot:action><x-ui.button :href="route('staff.students.import')" wire:navigate>Impor Santri</x-ui.button></x-slot:action>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="users" message="Belum ada santri yang bisa Anda lihat. Santri tampil setelah Anda ditugaskan ke kelas atau asrama." />
            @endif
        @else
            @if ($canManage && count($selected) > 0)
                <form wire:submit="moveSelected" class="mb-4 flex flex-col gap-3 rounded-control bg-primary-soft p-3 md:flex-row md:items-end">
                    <p class="text-base font-semibold md:mb-3">{{ count($selected) }} santri dipilih</p>
                    <div class="min-w-0 flex-1 md:max-w-xs">
                        <x-ui.select label="Pindahkan ke kelas" name="bulkClassroom" placeholder="Pilih kelas" :options="$classrooms" wire:model="bulkClassroom" />
                    </div>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="moveSelected">Pindahkan</x-ui.button>
                    <x-ui.button variant="tertiary" wire:click="$set('selected', [])">Batal pilih</x-ui.button>
                </form>
            @endif

            <x-ui.table caption="Daftar santri">
                <thead>
                    <tr>
                        @if ($canManage)<x-ui.th><span class="sr-only">Pilih</span></x-ui.th>@endif
                        <x-ui.th sortable :direction="$sort === 'name' ? $direction : null" wire:click="sortBy('name')">Nama</x-ui.th>
                        <x-ui.th sortable :direction="$sort === 'nis' ? $direction : null" wire:click="sortBy('nis')">NIS</x-ui.th>
                        <x-ui.th>Kelas</x-ui.th>
                        <x-ui.th>Asrama</x-ui.th>
                        <x-ui.th>Status</x-ui.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        <tr wire:key="student-{{ $student->uuid }}" class="hover:bg-primary-soft">
                            @if ($canManage)
                                <x-ui.td label="Pilih">
                                    <label class="inline-flex size-11 items-center justify-center">
                                        <span class="sr-only">Pilih {{ $student->name }}</span>
                                        <input type="checkbox" value="{{ $student->uuid }}" wire:model.live="selected" class="size-5 accent-primary">
                                    </label>
                                </x-ui.td>
                            @endif
                            <x-ui.td label="Nama"><a href="{{ route('staff.students.show', $student) }}" wire:navigate class="inline-flex min-h-11 items-center font-semibold text-primary hover:underline">{{ $student->name }}</a></x-ui.td>
                            <x-ui.td label="NIS"><span class="font-tabular">{{ $student->nis }}</span></x-ui.td>
                            <x-ui.td label="Kelas">{{ $student->classroom?->name ?? 'Belum ada' }}</x-ui.td>
                            <x-ui.td label="Asrama">{{ $student->dormitory?->name ?? 'Belum ada' }}</x-ui.td>
                            <x-ui.td label="Status">
                                @if ($student->status === 'active')
                                    <x-ui.status-badge status="active" />
                                @else
                                    {{ ['inactive' => 'Nonaktif', 'graduated' => 'Lulus', 'dropped' => 'Keluar'][$student->status] }}
                                @endif
                            </x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            <div class="mt-4">{{ $students->links() }}</div>
        @endif
    </x-ui.card>
</div>
