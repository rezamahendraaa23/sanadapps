<?php

namespace App\Modules\Core\Livewire\Students;

use App\Modules\Core\Actions\SaveGuardian;
use App\Modules\Core\Actions\SaveStudent;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\CustomFieldDefinition;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.staff', ['active' => 'students'])]
class StudentForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $studentId = null;

    public string $nis = '';

    public string $nisn = '';

    public string $name = '';

    public string $gender = '';

    public string $birth_place = '';

    public string $birth_date = '';

    public string $address = '';

    public string $status = 'active';

    public string $entry_date = '';

    public string $classroom_id = '';

    public string $dormitory_id = '';

    public string $guardian_name = '';

    public string $guardian_phone = '';

    public string $guardian_relation = 'ayah';

    public $photo = null;

    /** @var array<string, mixed> */
    public array $extra = [];

    public function mount(?Student $student = null): void
    {
        $this->authorize($student?->exists ? 'update' : 'create', $student?->exists ? $student : Student::class);

        if ($student?->exists) {
            $this->studentId = $student->id;
            $this->fill([
                'nis' => $student->nis,
                'nisn' => (string) $student->nisn,
                'name' => $student->name,
                'gender' => $student->gender,
                'birth_place' => (string) $student->birth_place,
                'birth_date' => (string) $student->birth_date?->format('Y-m-d'),
                'address' => (string) $student->address,
                'status' => $student->status,
                'entry_date' => (string) $student->entry_date?->format('Y-m-d'),
                'classroom_id' => (string) $student->classroom_id,
                'dormitory_id' => (string) $student->dormitory_id,
                'extra' => $student->extra ?? [],
            ]);
        }
    }

    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    private function customFields(): Collection
    {
        return CustomFieldDefinition::query()->where('entity', 'student')->where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'nis' => ['nullable', 'string', 'max:30', Rule::unique('students', 'nis')->ignore($this->studentId)],
            'nisn' => 'nullable|string|max:20',
            'name' => 'required|string|max:150',
            'gender' => 'required|in:L,P',
            'birth_place' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'status' => 'required|in:active,inactive,graduated,dropped',
            'entry_date' => 'nullable|date',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'dormitory_id' => 'nullable|exists:dormitories,id',
            'photo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'guardian_name' => 'required_with:guardian_phone|nullable|string|max:150',
            'guardian_phone' => 'nullable|string|max:30',
            'guardian_relation' => 'required|in:ayah,ibu,wali',
        ];

        foreach ($this->customFields() as $field) {
            $rules['extra.'.$field->key] = array_filter([
                $field->is_required ? 'required' : 'nullable',
                match ($field->type) {
                    'number' => 'numeric',
                    'date' => 'date',
                    'select' => Rule::in($field->options),
                    default => 'string',
                },
            ]);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nis' => 'NIS',
            'nisn' => 'NISN',
            'gender' => 'jenis kelamin',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'entry_date' => 'tanggal masuk',
            'photo' => 'foto',
            'guardian_name' => 'nama wali',
            'guardian_phone' => 'nomor WhatsApp wali',
            'guardian_relation' => 'hubungan',
            ...$this->customFields()->mapWithKeys(fn ($field) => ['extra.'.$field->key => mb_strtolower($field->label)])->all(),
        ];
    }

    public function save(SaveStudent $students, SaveGuardian $guardians)
    {
        $this->resetErrorBag();
        $validated = $this->validate();

        $phone = null;
        if (filled($this->guardian_phone)) {
            $phone = PhoneNumber::normalize($this->guardian_phone);

            if ($phone === null) {
                $this->addError('guardian_phone', 'Nomor WhatsApp wali belum benar. Contoh: 081234567890.');

                return null;
            }
        }

        $data = collect($validated)->only(['nis', 'nisn', 'name', 'gender', 'birth_place', 'birth_date', 'address', 'status', 'entry_date', 'classroom_id', 'dormitory_id'])
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all();
        $data['extra'] = $this->extra;

        if ($this->photo) {
            $data['photo_path'] = $this->photo->store('students', 'local');
        }

        $existing = $this->studentId ? Student::query()->findOrFail($this->studentId) : null;

        if ($existing !== null && $data['nis'] === null) {
            unset($data['nis']);
        }

        [$student, $password] = DB::transaction(function () use ($students, $guardians, $data, $existing, $phone): array {
            $result = $students->handle($data, $existing);

            if ($phone !== null) {
                $guardian = $guardians->handle(['name' => $this->guardian_name, 'phone_wa' => $phone]);
                $guardian->students()->syncWithoutDetaching([$result[0]->id => [
                    'relation' => $this->guardian_relation,
                    'is_primary' => ! $result[0]->guardians()->exists(),
                    'is_payer' => ! $result[0]->guardians()->exists(),
                ]]);
            }

            return $result;
        });

        session()->flash('toast', $existing ? 'Data santri tersimpan.' : 'Santri ditambahkan.');

        if ($password !== null) {
            session()->flash('initial_password', $password);
        }

        return $this->redirectRoute('staff.students.show', $student, navigate: true);
    }

    public function render(): View
    {
        return view('core.students.form', [
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
            'dormitories' => Dormitory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'fields' => $this->customFields(),
        ])->title($this->studentId ? 'Ubah Santri' : 'Tambah Santri');
    }
}
