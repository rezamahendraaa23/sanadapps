<?php

namespace App\Modules\Finance\Livewire\Portal;

use App\Modules\Finance\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'invoices', 'title' => 'Tagihan'])]
#[Title('Tagihan')]
class GuardianInvoices extends Component
{
    #[Url(except: 'belum')]
    public string $tab = 'belum';

    /** @var list<string> */
    public array $selected = [];

    public function pay()
    {
        $ids = Invoice::query()->visibleTo(auth()->user())->open()->whereIn('uuid', $this->selected)->pluck('uuid')->all();

        if ($ids === []) {
            $this->addError('selected', 'Pilih minimal satu tagihan.');

            return null;
        }

        return $this->redirectRoute('wali.checkout', ['tagihan' => implode(',', $ids)]);
    }

    public function render(): View
    {
        $base = Invoice::query()->visibleTo(auth()->user())->with('student:id,name');

        $invoices = $this->tab === 'riwayat'
            ? (clone $base)->whereIn('status', ['paid', 'void'])->latest('id')->limit(50)->get()
            : (clone $base)->open()->orderBy('due_date')->get();

        return view('finance.portal.invoices', [
            'invoices' => $invoices,
            'selectedTotal' => $this->tab === 'riwayat' ? 0 : $invoices->whereIn('uuid', $this->selected)->sum(fn (Invoice $invoice) => $invoice->outstanding()),
            'selectedCount' => count($this->selected),
        ]);
    }
}
