<?php

namespace App\Modules\Wallet\Livewire;

use App\Modules\Wallet\Models\CardScan;
use App\Modules\Wallet\Models\Device;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'cards'])]
#[Title('Log Pindai')]
class ScanLog extends Component
{
    use WithPagination;

    public const RESULTS = ['ok' => 'Berhasil', 'denied' => 'Ditolak', 'blocked' => 'Diblokir', 'not_found' => 'Tidak dikenal'];

    #[Url(as: 'hasil', except: '')]
    public string $result = '';

    #[Url(except: '')]
    public string $scope = '';

    #[Url(as: 'tanggal', except: '')]
    public string $date = '';

    public function updating(): void
    {
        $this->resetPage();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CardScan>
     */
    public static function query(array $filters): Builder
    {
        return CardScan::query()
            ->with(['card.student:id,name,nis', 'device:id,name'])
            ->when($filters['result'] ?? null, fn ($query, $value) => $query->where('result', $value))
            ->when($filters['scope'] ?? null, fn ($query, $value) => $query->where('scope', $value))
            ->when($filters['date'] ?? null, function ($query, $value) {
                $start = now(config('sanad.display_timezone'))->setDateFrom($value)->startOfDay()->utc();

                return $query->whereBetween('scanned_at', [$start, $start->copy()->addDay()]);
            })
            ->latest('id');
    }

    public function render(): View
    {
        return view('wallet.scans', [
            'scans' => self::query(['result' => $this->result, 'scope' => $this->scope, 'date' => $this->date])->paginate(30),
            'results' => self::RESULTS,
            'scopes' => Device::SCOPES,
        ]);
    }
}
