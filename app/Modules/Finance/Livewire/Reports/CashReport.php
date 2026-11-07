<?php

namespace App\Modules\Finance\Livewire\Reports;

use App\Modules\Finance\Models\CashCategory;
use App\Modules\Finance\Models\CashTransaction;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'cash'])]
#[Title('Laporan Kas')]
class CashReport extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(as: 'dari')]
    public string $from = '';

    #[Url(as: 'sampai')]
    public string $to = '';

    public string $form_type = 'out';

    public string $form_category = '';

    public string $form_amount = '';

    public string $form_date = '';

    public string $form_description = '';

    public $form_attachment = null;

    public function mount(): void
    {
        $now = now(config('sanad.display_timezone'));
        $this->from = $this->from ?: $now->copy()->startOfMonth()->toDateString();
        $this->to = $this->to ?: $now->toDateString();
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function openForm(): void
    {
        abort_unless(auth()->user()->can('finance.manage'), 403);
        $this->reset('form_type', 'form_category', 'form_amount', 'form_description', 'form_attachment');
        $this->form_date = now(config('sanad.display_timezone'))->toDateString();
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-kas');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('finance.manage'), 403);
        $this->resetErrorBag();
        $this->form_amount = preg_replace('/\D/', '', $this->form_amount) ?? '';

        $this->validate([
            'form_type' => 'required|in:in,out',
            'form_category' => 'required|exists:cash_categories,id',
            'form_amount' => 'required|integer|min:1|max:100000000000',
            'form_date' => 'required|date',
            'form_description' => 'required|string|max:500',
            'form_attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [], ['form_category' => 'kategori', 'form_amount' => 'nominal', 'form_date' => 'tanggal', 'form_description' => 'keterangan', 'form_attachment' => 'lampiran', 'form_type' => 'jenis']);

        CashTransaction::create([
            'type' => $this->form_type,
            'category_id' => (int) $this->form_category,
            'amount' => (int) $this->form_amount,
            'transaction_date' => $this->form_date,
            'description' => $this->form_description,
            'attachment_path' => $this->form_attachment?->store('cash', 'local'),
            'created_by' => auth()->id(),
        ]);

        $this->dispatch('close-modal', 'form-kas');
        $this->dispatch('toast', message: 'Catatan kas tersimpan.', tone: 'success');
    }

    private function query()
    {
        return CashTransaction::query()->whereBetween('transaction_date', [$this->from, $this->to]);
    }

    public function render(): View
    {
        $in = (int) $this->query()->where('type', 'in')->sum('amount');
        $out = (int) $this->query()->where('type', 'out')->sum('amount');

        return view('finance.reports.cash', [
            'totalIn' => $in,
            'totalOut' => $out,
            'transactions' => $this->query()->with('category:id,name')->latest('transaction_date')->latest('id')->paginate(25),
            'categories' => CashCategory::query()->where('type', $this->form_type)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
