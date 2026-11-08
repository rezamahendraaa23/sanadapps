<?php

namespace App\Modules\Finance\Livewire\Students;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Models\FeeType;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentDiscount;
use App\Modules\Finance\Models\StudentFeeAssignment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StudentFinance extends Component
{
    #[Locked]
    public int $studentId;

    public string $assign_fee = '';

    public string $assign_amount = '';

    public string $assign_start = '';

    public bool $assign_active = true;

    public string $discount_fee = '';

    public string $discount_type = 'percent';

    public string $discount_value = '';

    public string $discount_reason = '';

    public string $discount_from = '';

    public string $discount_to = '';

    public function mount(Student $student): void
    {
        abort_unless(auth()->user()->can('finance.view'), 403);
        $this->studentId = $student->id;
    }

    public function saveAssignment(): void
    {
        abort_unless(auth()->user()->can('finance.manage'), 403);
        $this->assign_amount = preg_replace('/\D/', '', $this->assign_amount) ?? '';

        $this->validate([
            'assign_fee' => 'required|exists:fee_types,id',
            'assign_amount' => 'nullable|integer|min:0|max:1000000000',
            'assign_start' => 'required|date',
        ], [], ['assign_fee' => 'jenis tagihan', 'assign_amount' => 'nominal khusus', 'assign_start' => 'mulai bulan']);

        StudentFeeAssignment::create([
            'student_id' => $this->studentId,
            'fee_type_id' => (int) $this->assign_fee,
            'custom_amount' => $this->assign_amount === '' ? null : (int) $this->assign_amount,
            'start_month' => $this->assign_start,
            'is_active' => $this->assign_active,
        ]);

        $this->reset('assign_fee', 'assign_amount', 'assign_start', 'assign_active');
        $this->dispatch('close-modal', 'form-penugasan');
        $this->dispatch('toast', message: 'Penugasan tersimpan.', tone: 'success');
    }

    public function endAssignment(string $uuid): void
    {
        abort_unless(auth()->user()->can('finance.manage'), 403);
        StudentFeeAssignment::query()->where('student_id', $this->studentId)->where('uuid', $uuid)->firstOrFail()
            ->update(['end_month' => now(config('sanad.display_timezone'))->toDateString()]);
    }

    public function saveDiscount(): void
    {
        abort_unless(auth()->user()->can('finance.manage'), 403);
        $this->discount_value = preg_replace('/\D/', '', $this->discount_value) ?? '';

        $this->validate([
            'discount_fee' => 'nullable|exists:fee_types,id',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => ['required', 'integer', 'min:1', $this->discount_type === 'percent' ? 'max:100' : 'max:1000000000'],
            'discount_reason' => 'required|string|max:200',
            'discount_from' => 'required|date',
            'discount_to' => 'nullable|date|after_or_equal:discount_from',
        ], [], ['discount_value' => 'nilai diskon', 'discount_reason' => 'alasan', 'discount_from' => 'berlaku mulai', 'discount_to' => 'berlaku sampai']);

        StudentDiscount::create([
            'student_id' => $this->studentId,
            'fee_type_id' => $this->discount_fee === '' ? null : (int) $this->discount_fee,
            'type' => $this->discount_type,
            'value' => (int) $this->discount_value,
            'reason' => $this->discount_reason,
            'valid_from' => $this->discount_from,
            'valid_to' => $this->discount_to ?: null,
            'approved_by' => auth()->id(),
        ]);

        $this->reset('discount_fee', 'discount_type', 'discount_value', 'discount_reason', 'discount_from', 'discount_to');
        $this->dispatch('close-modal', 'form-diskon');
        $this->dispatch('toast', message: 'Diskon tersimpan. Berlaku untuk tagihan berikutnya.', tone: 'success');
    }

    public function render(): View
    {
        return view('finance.students.student-finance', [
            'invoices' => Invoice::query()->where('student_id', $this->studentId)->latest('id')->limit(24)->get(),
            'assignments' => StudentFeeAssignment::query()->with('feeType:id,name')->where('student_id', $this->studentId)->latest('start_month')->get(),
            'discounts' => StudentDiscount::query()->with('feeType:id,name')->where('student_id', $this->studentId)->latest('valid_from')->get(),
            'payments' => Payment::query()->whereHas('invoices', fn ($query) => $query->where('student_id', $this->studentId))->latest('id')->limit(12)->get(),
            'feeTypes' => FeeType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'canManage' => auth()->user()->can('finance.manage'),
            'studentUuid' => Student::query()->whereKey($this->studentId)->value('uuid'),
        ]);
    }
}
