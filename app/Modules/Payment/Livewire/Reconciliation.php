<?php

namespace App\Modules\Payment\Livewire;

use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Actions\SyncPayment;
use App\Modules\Payment\Support\GatewayException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'payments'])]
#[Title('Rekonsiliasi Midtrans')]
class Reconciliation extends Component
{
    use WithPagination;

    #[Url(as: 'tampil', except: 'perlu')]
    public string $filter = 'perlu';

    public string $resolveNote = '';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function sync(string $uuid, SyncPayment $action): void
    {
        $payment = Payment::query()->where('method', 'midtrans')->where('uuid', $uuid)->firstOrFail();

        try {
            $status = $action->handle($payment);
            $this->dispatch('toast', message: $status === null ? "{$payment->payment_no} tidak dikenal Midtrans." : "{$payment->payment_no} tersinkron: {$status}.", tone: $status === null ? 'danger' : 'success');
        } catch (GatewayException) {
            $this->dispatch('toast', message: 'Tidak tersambung ke Midtrans. Coba lagi beberapa saat.', tone: 'danger');
        }
    }

    public function syncAll(SyncPayment $action): void
    {
        abort_unless(auth()->user()->can('finance.verify'), 403);

        $this->dispatch('toast', message: $action->pending().' pembayaran disinkronkan.', tone: 'success');
    }

    public function resolve(string $uuid): void
    {
        abort_unless(auth()->user()->can('finance.verify'), 403);

        if (trim($this->resolveNote) === '') {
            $this->addError('resolveNote', 'Tulis tindakan yang sudah dilakukan.');

            return;
        }

        Payment::query()->where('uuid', $uuid)->where('needs_review', true)->firstOrFail()->update([
            'needs_review' => false,
            'review_note' => trim($this->resolveNote),
        ]);

        $this->reset('resolveNote');
        $this->resetErrorBag();
        $this->dispatch('toast', message: 'Ditandai selesai.', tone: 'success');
    }

    public function render(): View
    {
        return view('payment.reconciliation', [
            'payments' => Payment::query()
                ->where('method', 'midtrans')
                ->with('invoices.student:id,name', 'topup.wallet.student:id,name')
                ->when($this->filter === 'perlu', fn ($query) => $query->where(fn ($inner) => $inner->where('needs_review', true)->orWhere('status', 'pending')))
                ->latest('id')
                ->paginate(25),
            'canAct' => auth()->user()->can('finance.verify'),
        ]);
    }
}
