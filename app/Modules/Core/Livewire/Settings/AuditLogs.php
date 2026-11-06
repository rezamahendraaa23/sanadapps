<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'audit'])]
#[Title('Audit Log')]
class AuditLogs extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $userId = '';

    #[Url(except: '')]
    public string $entity = '';

    #[Url(except: '')]
    public string $date = '';

    #[Locked]
    public ?int $detailId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['userId', 'entity', 'date'], true)) {
            $this->resetPage();
        }
    }

    public function showDetail(int $id): void
    {
        $this->detailId = $id;
        $this->dispatch('open-modal', 'detail-audit');
    }

    public function clearFilters(): void
    {
        $this->reset('userId', 'entity', 'date');
        $this->resetPage();
    }

    public function render(): View
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($this->userId !== '', fn ($query) => $query->where('user_id', (int) $this->userId))
            ->when($this->entity !== '', fn ($query) => $query->where('entity_type', $this->entity))
            ->when($this->date !== '' && strtotime($this->date) !== false, fn ($query) => $query->whereBetween('created_at', [
                now()->parse($this->date, config('sanad.display_timezone'))->startOfDay()->utc(),
                now()->parse($this->date, config('sanad.display_timezone'))->endOfDay()->utc(),
            ]))
            ->latest('id')
            ->paginate(25);

        return view('core.settings.audit-logs', [
            'logs' => $logs,
            'users' => User::query()->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))->orderBy('name')->pluck('name', 'id')->all(),
            'entities' => AuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type', 'entity_type')->all(),
            'detail' => $this->detailId ? AuditLog::with('user:id,name')->find($this->detailId) : null,
        ]);
    }
}
