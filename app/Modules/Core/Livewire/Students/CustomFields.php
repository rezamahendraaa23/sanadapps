<?php

namespace App\Modules\Core\Livewire\Students;

use App\Modules\Core\Models\CustomFieldDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'students'])]
#[Title('Field Tambahan Santri')]
class CustomFields extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $label = '';

    public string $type = 'text';

    public string $options = '';

    public bool $is_required = false;

    public bool $is_active = true;

    public function create(): void
    {
        $this->reset('editingId', 'label', 'type', 'options', 'is_required', 'is_active');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-field');
    }

    public function edit(string $uuid): void
    {
        $field = CustomFieldDefinition::query()->where('uuid', $uuid)->firstOrFail();
        $this->editingId = $field->id;
        $this->fill([
            'label' => $field->label,
            'type' => $field->type,
            'options' => implode(', ', $field->options),
            'is_required' => $field->is_required,
            'is_active' => $field->is_active,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-field');
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $this->validate([
            'label' => 'required|string|max:100',
            'type' => 'required|in:text,number,select,date',
            'options' => 'required_if:type,select|nullable|string|max:1000',
        ], ['options.required_if' => 'Isi pilihan, pisahkan dengan koma.'], ['label' => 'nama field', 'type' => 'jenis']);

        $options = $this->type === 'select' ? array_values(array_filter(array_map('trim', explode(',', $this->options)))) : [];
        $data = ['label' => $this->label, 'type' => $this->type, 'options' => $options, 'is_required' => $this->is_required, 'is_active' => $this->is_active];

        if ($this->editingId) {
            CustomFieldDefinition::query()->findOrFail($this->editingId)->update($data);
        } else {
            $key = Str::slug($this->label, '_');
            $base = $key === '' ? 'field' : $key;
            $key = $base;

            for ($suffix = 2; CustomFieldDefinition::query()->where('entity', 'student')->where('key', $key)->exists(); $suffix++) {
                $key = $base.'_'.$suffix;
            }

            CustomFieldDefinition::create([...$data, 'entity' => 'student', 'key' => $key, 'sort_order' => (int) CustomFieldDefinition::query()->max('sort_order') + 1]);
        }

        $this->dispatch('close-modal', 'form-field');
        $this->dispatch('toast', message: 'Field tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        return view('core.students.custom-fields', [
            'fields' => CustomFieldDefinition::query()->where('entity', 'student')->orderBy('sort_order')->get(),
        ]);
    }
}
