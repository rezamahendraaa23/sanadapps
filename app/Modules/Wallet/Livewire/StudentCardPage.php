<?php

namespace App\Modules\Wallet\Livewire;

use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Support\QrToken;
use App\Support\Qr;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'santri', 'active' => 'card', 'title' => 'Kartu'])]
#[Title('Kartu')]
class StudentCardPage extends Component
{
    public function render(ModuleRegistry $modules): View
    {
        $student = auth()->user()->student?->load('classroom');
        $card = $student ? StudentCard::query()->where('student_id', $student->id)->latest('id')->first() : null;
        $active = $card?->status === 'active';

        return view('wallet.student-card', [
            'student' => $student,
            'card' => $card,
            'active' => $active,
            'qr' => $active ? Qr::svg(QrToken::dynamic($card), 240) : null,
            'secondsLeft' => QrToken::secondsLeft(),
            'balance' => $student && $modules->enabled('wallet') ? Wallet::forStudent($student)->balance : null,
        ]);
    }
}
