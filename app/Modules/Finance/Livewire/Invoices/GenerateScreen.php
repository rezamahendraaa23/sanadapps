<?php

namespace App\Modules\Finance\Livewire\Invoices;

use App\Modules\Core\Models\Classroom;
use App\Modules\Finance\Actions\GenerateInvoices;
use App\Modules\Finance\Models\FeeType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'invoices'])]
#[Title('Generate Tagihan')]
class GenerateScreen extends Component
{
    public string $period = '';

    public string $classroom = '';

    /** @var list<string> */
    public array $feeTypeIds = [];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $preview = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $result = null;

    public function mount(): void
    {
        $this->period = now(config('sanad.display_timezone'))->format('Y-m');
        $this->feeTypeIds = FeeType::query()->where('is_active', true)->where('category', 'monthly')->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * Mengubah pilihan membatalkan pratinjau: generate hanya boleh sesuai yang sudah dilihat.
     */
    public function updated(): void
    {
        $this->preview = null;
        $this->result = null;
    }

    public function runPreview(GenerateInvoices $action): void
    {
        $this->validateInputs();
        $this->result = null;
        $this->preview = $action->preview(...$this->arguments());
    }

    public function generate(GenerateInvoices $action): void
    {
        abort_if($this->preview === null, 422);
        $this->validateInputs();

        $this->result = $action->run(...[...$this->arguments(), 'userId' => auth()->id()]);
        $this->preview = null;
        $this->dispatch('close-modal', 'konfirmasi-generate');
        $this->dispatch('toast', message: "{$this->result['invoices']} tagihan dibuat.", tone: 'success');
    }

    private function validateInputs(): void
    {
        $this->validate([
            'period' => 'required|date_format:Y-m',
            'feeTypeIds' => 'required|array|min:1',
            'feeTypeIds.*' => 'exists:fee_types,id',
            'classroom' => 'nullable|exists:classrooms,id',
        ], ['feeTypeIds.required' => 'Pilih minimal satu jenis tagihan.', 'feeTypeIds.min' => 'Pilih minimal satu jenis tagihan.', 'period.required' => 'Pilih periode.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function arguments(): array
    {
        return [
            'period' => CarbonImmutable::createFromFormat('!Y-m', $this->period),
            'feeTypeIds' => array_map('intval', $this->feeTypeIds),
            'classroomId' => $this->classroom === '' ? null : (int) $this->classroom,
        ];
    }

    public function render(): View
    {
        return view('finance.invoices.generate', [
            'feeTypes' => FeeType::query()->where('is_active', true)->orderBy('name')->get(),
            'classrooms' => Classroom::query()->ordered()->pluck('name', 'id')->all(),
        ]);
    }
}
