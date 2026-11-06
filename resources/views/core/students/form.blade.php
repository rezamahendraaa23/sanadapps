<form wire:submit="save" class="mx-auto flex w-full max-w-[720px] flex-col gap-4">
    <x-ui.card title="Identitas">
        <div class="flex flex-col gap-4">
            <x-ui.input label="Nama lengkap" name="name" required wire:model="name" />
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="NIS" name="nis" wire:model="nis" help="Kosongkan agar dibuat otomatis." />
                <x-ui.input label="NISN" name="nisn" inputmode="numeric" wire:model="nisn" />
            </div>
            <x-ui.select label="Jenis kelamin" name="gender" required placeholder="Pilih" :options="['L' => 'Laki-laki', 'P' => 'Perempuan']" wire:model="gender" />
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Tempat lahir" name="birth_place" wire:model="birth_place" />
                <x-ui.input label="Tanggal lahir" name="birth_date" type="date" wire:model="birth_date" />
            </div>
            <x-ui.textarea label="Alamat" name="address" wire:model="address" />
            <div class="flex flex-col gap-1">
                <label for="field-photo" class="text-sm font-semibold">Foto</label>
                <input id="field-photo" type="file" accept="image/png,image/jpeg,image/webp" capture="environment" wire:model="photo" class="min-h-11 w-full min-w-0 text-sm">
                <p class="text-sm text-text-muted">PNG, JPG, atau WebP, maksimal 2 MB.</p>
                @error('photo')
                    <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                @enderror
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Akademik">
        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.select label="Kelas" name="classroom_id" placeholder="Belum ditentukan" :options="$classrooms" wire:model="classroom_id" />
            <x-ui.select label="Asrama" name="dormitory_id" placeholder="Belum ditentukan" :options="$dormitories" wire:model="dormitory_id" />
            <x-ui.input label="Tanggal masuk" name="entry_date" type="date" wire:model="entry_date" />
            <x-ui.select label="Status" name="status" required :options="['active' => 'Aktif', 'inactive' => 'Nonaktif', 'graduated' => 'Lulus', 'dropped' => 'Keluar']" wire:model="status" />
        </div>
    </x-ui.card>

    <x-ui.card title="Tambah wali">
        <div class="flex flex-col gap-4">
            <x-ui.input label="Nomor WhatsApp wali" name="guardian_phone" type="tel" inputmode="tel" wire:model="guardian_phone" help="Wali masuk ke portal dengan nomor ini. Nomor yang sudah terdaftar otomatis terhubung ke wali yang sama." />
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Nama wali" name="guardian_name" wire:model="guardian_name" />
                <x-ui.select label="Hubungan" name="guardian_relation" :options="['ayah' => 'Ayah', 'ibu' => 'Ibu', 'wali' => 'Wali']" wire:model="guardian_relation" />
            </div>
        </div>
    </x-ui.card>

    @if ($fields->isNotEmpty())
        <x-ui.card title="Data tambahan">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($fields as $field)
                    @if ($field->type === 'select')
                        <x-ui.select :label="$field->label" :name="'extra.'.$field->key" :required="$field->is_required" placeholder="Pilih" :options="array_combine($field->options, $field->options)" wire:model="extra.{{ $field->key }}" />
                    @else
                        <x-ui.input :label="$field->label" :name="'extra.'.$field->key" :required="$field->is_required" :type="$field->type === 'date' ? 'date' : 'text'" :inputmode="$field->type === 'number' ? 'decimal' : null" wire:model="extra.{{ $field->key }}" />
                    @endif
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <div class="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
        <x-ui.button variant="secondary" :href="$studentId ? route('staff.students.show', \App\Modules\Core\Models\Student::find($studentId)) : route('staff.students.index')">Batal</x-ui.button>
        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save,photo">
            <span wire:loading.remove wire:target="save">Simpan Santri</span>
            <span wire:loading wire:target="save">Menyimpan…</span>
        </x-ui.button>
    </div>
</form>
