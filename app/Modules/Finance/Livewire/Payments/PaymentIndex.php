<?php

namespace App\Modules\Finance\Livewire\Payments;

use App\Modules\Finance\Models\Payment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'payments'])]
#[Title('Pembayaran')]
class PaymentIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'cara', except: '')]
    public string $method = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['status', 'method'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        return view('finance.payments.index', [
            'payments' => Payment::query()
                ->visibleTo(auth()->user())
                ->with('invoices.student:id,name')
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->when($this->method !== '', fn ($query) => $query->where('method', $this->method))
                ->latest('id')
                ->paginate(25),
        ]);
    }
}
