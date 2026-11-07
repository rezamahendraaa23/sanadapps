<?php

namespace App\Modules\Finance\Livewire\Settings;

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\ResolveFeeRate;
use App\Modules\Finance\Models\FeeRate;
use App\Modules\Finance\Models\FeeType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'fee-types'])]
#[Title('Jenis Tagihan')]
class FeeTypes extends Component
{
    #[Url(as: 'jenis', except: '')]
    public string $selected = '';

    #[Locked]
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $category = 'monthly';

    public string $due_day = '10';

    public bool $is_mandatory = true;

    public bool $allow_partial = false;

    public string $late_fee_type = '';

    public string $late_fee_value = '';

    public string $late_fee_after_days = '';

    public bool $is_active = true;

    public string $rate_scope = 'default';

    public string $rate_target = '';

    public string $rate_amount = '';

    public string $rate_from = '';

    public string $rate_to = '';

    public string $previewNis = '';

    public function create(): void
    {
        $this->reset('editingId', 'code', 'name', 'category', 'due_day', 'is_mandatory', 'allow_partial', 'late_fee_type', 'late_fee_value', 'late_fee_after_days', 'is_active');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-jenis');
    }

    public function edit(string $uuid): void
    {
        $type = FeeType::query()->where('uuid', $uuid)->firstOrFail();
        $this->editingId = $type->id;
        $this->fill([
            'code' => $type->code,
            'name' => $type->name,
            'category' => $type->category,
            'due_day' => (string) $type->due_day,
            'is_mandatory' => $type->is_mandatory,
            'allow_partial' => $type->allow_partial,
            'late_fee_type' => (string) $type->late_fee_type,
            'late_fee_value' => (string) $type->late_fee_value,
            'late_fee_after_days' => (string) $type->late_fee_after_days,
            'is_active' => $type->is_active,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-jenis');
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $this->code = mb_strtoupper(trim($this->code));

        $this->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('fee_types', 'code')->ignore($this->editingId)],
            'name' => 'required|string|max:100',
            'category' => 'required|in:monthly,yearly,one_time,custom',
            'due_day' => 'nullable|integer|min:1|max:31',
            'late_fee_type' => 'nullable|in:fixed,percent',
            'late_fee_value' => ['nullable', 'required_with:late_fee_type', 'integer', 'min:1', $this->late_fee_type === 'percent' ? 'max:100' : 'max:100000000'],
            'late_fee_after_days' => 'nullable|integer|min:0|max:365',
        ], ['code.regex' => 'Kode hanya huruf, angka, garis bawah, atau strip.'], ['code' => 'kode', 'category' => 'kategori', 'due_day' => 'hari jatuh tempo', 'late_fee_value' => 'nilai denda', 'late_fee_after_days' => 'hari tenggang']);

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'due_day' => $this->due_day === '' ? null : (int) $this->due_day,
            'is_mandatory' => $this->is_mandatory,
            'allow_partial' => $this->allow_partial,
            'late_fee_type' => $this->late_fee_type ?: null,
            'late_fee_value' => $this->late_fee_type ? (int) $this->late_fee_value : null,
            'late_fee_after_days' => $this->late_fee_type ? (int) $this->late_fee_after_days : null,
            'is_active' => $this->is_active,
        ];

        $type = $this->editingId
            ? tap(FeeType::query()->findOrFail($this->editingId))->update($data)
            : FeeType::create($data);

        $this->selected = $type->uuid;
        $this->dispatch('close-modal', 'form-jenis');
        $this->dispatch('toast', message: 'Jenis tagihan tersimpan.', tone: 'success');
    }

    public function addRate(): void
    {
        $this->resetErrorBag();
        $type = FeeType::query()->where('uuid', $this->selected)->firstOrFail();
        $this->rate_amount = preg_replace('/\D/', '', $this->rate_amount) ?? '';

        $this->validate([
            'rate_scope' => 'required|in:default,level,classroom,dormitory,student',
            'rate_target' => 'required_unless:rate_scope,default|nullable|string|max:50',
            'rate_amount' => 'required|integer|min:0|max:1000000000',
            'rate_from' => 'required|date',
            'rate_to' => 'nullable|date|after_or_equal:rate_from',
        ], ['rate_target.required_unless' => 'Pilih sasaran tarif.'], ['rate_amount' => 'nominal', 'rate_from' => 'berlaku mulai', 'rate_to' => 'berlaku sampai']);

        $scopeId = null;
        $scopeLevel = null;

        switch ($this->rate_scope) {
            case 'level':
                $scopeLevel = trim($this->rate_target);
                break;
            case 'student':
                $scopeId = Student::query()->where('nis', trim($this->rate_target))->value('id');
                if ($scopeId === null) {
                    $this->addError('rate_target', 'NIS tidak ditemukan.');

                    return;
                }
                break;
            case 'classroom':
            case 'dormitory':
                $scopeId = (int) $this->rate_target;
                break;
        }

        $overlap = FeeRate::query()
            ->where('fee_type_id', $type->id)
            ->where('scope', $this->rate_scope)
            ->where('scope_id', $scopeId)
            ->where('scope_level', $scopeLevel)
            ->when($scopeId === null, fn ($query) => $query->orWhere(fn ($inner) => $inner->where('fee_type_id', $type->id)->where('scope', $this->rate_scope)->whereNull('scope_id')->where(fn ($level) => $scopeLevel === null ? $level->whereNull('scope_level') : $level->where('scope_level', $scopeLevel))))
            ->get()
            ->first(fn (FeeRate $rate) => $rate->valid_from->toDateString() <= ($this->rate_to ?: '9999-12-31')
                && ($rate->valid_to?->toDateString() ?? '9999-12-31') >= $this->rate_from);

        if ($overlap !== null) {
            $this->addError('rate_from', 'Masa berlaku bertabrakan dengan tarif lain untuk sasaran yang sama. Akhiri tarif lama dulu.');

            return;
        }

        FeeRate::create([
            'fee_type_id' => $type->id,
            'scope' => $this->rate_scope,
            'scope_id' => $scopeId,
            'scope_level' => $scopeLevel,
            'amount' => (int) $this->rate_amount,
            'valid_from' => $this->rate_from,
            'valid_to' => $this->rate_to ?: null,
        ]);

        $this->reset('rate_target', 'rate_amount', 'rate_from', 'rate_to');
        $this->dispatch('toast', message: 'Tarif ditambahkan.', tone: 'success');
    }

    public function endRate(string $uuid): void
    {
        $rate = FeeRate::query()->where('uuid', $uuid)->firstOrFail();
        $rate->update(['valid_to' => now(config('sanad.display_timezone'))->toDateString()]);
        $this->dispatch('toast', message: 'Tarif diakhiri hari ini.', tone: 'success');
    }

    public function render(ResolveFeeRate $resolver): View
    {
        $type = $this->selected !== '' ? FeeType::query()->where('uuid', $this->selected)->first() : null;
        $classrooms = Classroom::query()->orderBy('name')->pluck('name', 'id');
        $dormitories = Dormitory::query()->orderBy('name')->pluck('name', 'id');

        $preview = null;
        if ($type && trim($this->previewNis) !== '') {
            $student = Student::query()->with('classroom')->where('nis', trim($this->previewNis))->first();
            $preview = $student === null
                ? ['found' => false]
                : ['found' => true, 'name' => $student->name, 'rate' => $resolver->handle($student, $type, CarbonImmutable::now(config('sanad.display_timezone')))];
        }

        return view('finance.settings.fee-types', [
            'types' => FeeType::query()->withCount('rates')->orderBy('name')->get(),
            'type' => $type,
            'rates' => $type?->rates()->orderByDesc('valid_from')->get() ?? collect(),
            'classrooms' => $classrooms,
            'dormitories' => $dormitories,
            'levels' => Classroom::query()->whereNotNull('level')->distinct()->orderBy('level')->pluck('level', 'level'),
            'students' => $type ? Student::withTrashed()->whereIn('id', $type->rates()->where('scope', 'student')->pluck('scope_id'))->pluck('name', 'id') : collect(),
            'preview' => $preview,
        ]);
    }
}
