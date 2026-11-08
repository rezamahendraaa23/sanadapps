{{-- Form santri dalam satu alur bernomor; dipakai di popup Tambah/Ubah maupun halaman penuh. --}}
<form wire:submit="save" @class(['flex w-full flex-col', 'mx-auto max-w-[720px] rounded-card border border-border bg-surface p-4 shadow-sm md:p-6' => ! $inModal])>
    <div class="flex flex-col gap-6">
        {{-- 1. Identitas --}}
        <section class="flex flex-col gap-4" aria-labelledby="bagian-identitas">
            <h3 id="bagian-identitas" class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">1</span>Identitas</h3>

            <div class="flex flex-col gap-4 md:flex-row md:items-start">
                <div class="flex shrink-0 flex-col items-center gap-2">
                    <div class="flex size-24 items-center justify-center overflow-hidden rounded-full border border-border bg-bg">
                        @if ($photo && method_exists($photo, 'temporaryUrl'))
                            <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto" class="size-full object-cover">
                        @elseif ($currentPhoto)
                            <img src="{{ $currentPhoto }}" alt="Foto santri" class="size-full object-cover">
                        @else
                            <x-ui.icon name="user-round" size="lg" class="text-text-muted" />
                        @endif
                    </div>
                    <label for="field-photo" class="inline-flex min-h-11 cursor-pointer items-center gap-1 rounded-control px-3 text-sm font-semibold text-primary hover:bg-primary-soft focus-within:outline-2 focus-within:outline-primary">
                        <x-ui.icon name="camera" size="sm" /><span wire:loading.remove wire:target="photo">{{ $photo || $currentPhoto ? 'Ganti foto' : 'Pilih foto' }}</span><span wire:loading wire:target="photo">Mengunggah</span>
                        <input id="field-photo" type="file" accept="image/png,image/jpeg,image/webp" capture="environment" wire:model="photo" class="sr-only">
                    </label>
                    <p class="text-center text-xs text-text-muted">Opsional, maks. 2 MB</p>
                    @error('photo')<p role="alert" class="max-w-40 text-center text-sm text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="flex min-w-0 flex-1 flex-col gap-4">
                    <x-ui.input label="Nama lengkap" name="name" required wire:model="name" />
                    <fieldset class="flex flex-col gap-1">
                        <legend class="mb-1 text-sm font-semibold">Jenis kelamin <span class="text-danger" aria-hidden="true">*</span></legend>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['L' => 'Putra', 'P' => 'Putri'] as $value => $label)
                                <label class="relative flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-control border border-text-muted px-3 text-base font-semibold has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary has-focus-visible:outline-2 has-focus-visible:outline-primary">
                                    {{-- Radio transparan menutupi seluruh tombol: klik dan sentuhan langsung mengenainya. --}}
                                    <input type="radio" name="gender" value="{{ $value }}" wire:model="gender" class="absolute inset-0 size-full cursor-pointer appearance-none rounded-control opacity-0">{{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('gender')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
                    </fieldset>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Tempat lahir" name="birth_place" wire:model="birth_place" />
                <x-ui.input label="Tanggal lahir" name="birth_date" type="date" wire:model="birth_date" />
                <x-ui.input label="NIS" name="nis" wire:model="nis" help="Kosongkan, dibuat otomatis." />
                <x-ui.input label="NISN" name="nisn" inputmode="numeric" wire:model="nisn" help="Opsional." />
            </div>
            <x-ui.textarea label="Alamat" name="address" rows="2" wire:model="address" />
        </section>

        {{-- 2. Kelas dan asrama --}}
        <section class="flex flex-col gap-4 border-t border-border pt-5" aria-labelledby="bagian-kelas">
            <h3 id="bagian-kelas" class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">2</span>Kelas dan asrama</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.select label="Kelas" name="classroom_id" placeholder="Belum ditentukan" :options="$classrooms" wire:model="classroom_id" />
                <x-ui.select label="Asrama" name="dormitory_id" placeholder="Belum ditentukan" :options="$dormitories" wire:model="dormitory_id" />
                <x-ui.input label="Tanggal masuk" name="entry_date" type="date" wire:model="entry_date" />
                @if ($studentId)
                    <x-ui.select label="Status" name="status" required :options="['active' => 'Aktif', 'inactive' => 'Nonaktif', 'graduated' => 'Lulus', 'dropped' => 'Keluar']" wire:model="status" />
                @endif
            </div>
        </section>

        {{-- 3. Wali --}}
        <section class="flex flex-col gap-4 border-t border-border pt-5" aria-labelledby="bagian-wali">
            <h3 id="bagian-wali" class="flex items-center gap-2 text-base font-semibold">
                <span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">3</span>{{ $studentId ? 'Tambah wali' : 'Wali' }}
                <span class="text-sm font-normal text-text-muted">(opsional)</span>
            </h3>
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.input label="Nomor WhatsApp" name="guardian_phone" type="tel" inputmode="tel" placeholder="0812..." wire:model="guardian_phone" help="Untuk masuk portal wali. Nomor yang sudah terdaftar langsung terhubung." />
                <x-ui.input label="Nama wali" name="guardian_name" wire:model="guardian_name" />
            </div>
            <fieldset class="flex flex-col gap-1">
                <legend class="mb-1 text-sm font-semibold">Hubungan</legend>
                <div class="grid grid-cols-3 gap-2 md:max-w-md">
                    @foreach (['ayah' => 'Ayah', 'ibu' => 'Ibu', 'wali' => 'Wali'] as $value => $label)
                        <label class="relative flex min-h-11 cursor-pointer items-center justify-center rounded-control border border-text-muted px-3 text-base font-semibold has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary has-focus-visible:outline-2 has-focus-visible:outline-primary">
                            <input type="radio" name="guardian_relation" value="{{ $value }}" wire:model="guardian_relation" class="absolute inset-0 size-full cursor-pointer appearance-none rounded-control opacity-0">{{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        {{-- 4. Data tambahan dari Field Tambahan --}}
        @if ($fields->isNotEmpty())
            <section class="flex flex-col gap-4 border-t border-border pt-5" aria-labelledby="bagian-tambahan">
                <h3 id="bagian-tambahan" class="flex items-center gap-2 text-base font-semibold"><span class="inline-flex size-7 items-center justify-center rounded-full bg-primary text-sm text-on-primary">4</span>Data tambahan</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($fields as $field)
                        @if ($field->type === 'select')
                            <x-ui.select :label="$field->label" :name="'extra.'.$field->key" :required="$field->is_required" placeholder="Pilih" :options="array_combine($field->options, $field->options)" wire:model="extra.{{ $field->key }}" />
                        @else
                            <x-ui.input :label="$field->label" :name="'extra.'.$field->key" :required="$field->is_required" :type="$field->type === 'date' ? 'date' : 'text'" :inputmode="$field->type === 'number' ? 'decimal' : null" wire:model="extra.{{ $field->key }}" />
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Tombol menempel di bawah agar selalu terlihat walau form panjang. --}}
    <div @class(['flex flex-col-reverse gap-2 border-t border-border bg-surface pt-4 md:flex-row md:justify-end', 'sticky -bottom-4 z-10 -mx-4 mt-6 px-4 pb-4 md:-mx-6 md:px-6' => $inModal, 'mt-6' => ! $inModal])>
        @if ($inModal)
            <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', '{{ $modalName }}')">Batal</x-ui.button>
        @else
            <x-ui.button variant="secondary" :href="$studentId ? route('staff.students.show', \App\Modules\Core\Models\Student::find($studentId)) : route('staff.students.index')" wire:navigate>Batal</x-ui.button>
        @endif
        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save,photo">
            <span wire:loading.remove wire:target="save">{{ $studentId ? 'Simpan Perubahan' : 'Simpan Santri' }}</span>
            <span wire:loading wire:target="save">Menyimpan</span>
        </x-ui.button>
    </div>
</form>
