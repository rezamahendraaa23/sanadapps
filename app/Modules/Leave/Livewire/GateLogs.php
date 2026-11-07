<?php

namespace App\Modules\Leave\Livewire;

use App\Modules\Leave\Models\GateLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'gate'])]
#[Title('Riwayat Gerbang')]
class GateLogs extends Component
{
    use WithPagination;

    #[Url(as: 'tanggal', except: '')]
    public string $date = '';

    #[Url(as: 'hasil', except: '')]
    public string $result = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    /**
     * @return Builder<GateLog>
     */
    public static function query(?string $date, ?string $result): Builder
    {
        return GateLog::query()->with(['student:id,name,nis', 'recorder:id,name', 'device:id,name'])
            ->when(in_array($result, array_keys(GateLog::RESULTS), true), fn ($query) => $query->where('result', $result))
            ->when(is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]), function ($query) use ($date) {
                $start = now(config('sanad.display_timezone'))->setDateFrom($date)->startOfDay()->utc();

                return $query->whereBetween('occurred_at', [$start, $start->copy()->addDay()]);
            })
            ->latest('id');
    }

    public function render(): View
    {
        return view('leave.gate-logs', [
            'logs' => self::query($this->date, $this->result)->paginate(30),
            'results' => GateLog::RESULTS,
            'methods' => GateLog::METHODS,
        ]);
    }
}
