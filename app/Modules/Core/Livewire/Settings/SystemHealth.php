<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Models\WebhookEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.staff', ['active' => 'system'])]
#[Title('Kesehatan Sistem')]
class SystemHealth extends Component
{
    public function retry(string $uuid): void
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $this->dispatch('toast', message: 'Job dimasukkan ulang ke antrean.', tone: 'success');
    }

    private function queueSize(): ?int
    {
        try {
            return Queue::size();
        } catch (Throwable) {
            return null;
        }
    }

    public function render(): View
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        return view('core.settings.system-health', [
            'queueSize' => $this->queueSize(),
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(50)->get()->map(fn ($job) => (object) [
                'uuid' => $job->uuid,
                'name' => class_basename(json_decode($job->payload)->displayName ?? 'Job'),
                'failed_at' => $job->failed_at,
                'error' => strtok($job->exception, "\n"),
            ]),
            'lastWebhook' => WebhookEvent::query()->latest('id')->first(),
            'freeGb' => $free !== false ? round($free / 1073741824, 1) : null,
            'usedPercent' => $free !== false && $total ? (int) round((1 - $free / $total) * 100) : null,
        ]);
    }
}
