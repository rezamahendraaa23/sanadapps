<?php

namespace App\Modules\Notification\Livewire;

use App\Modules\Notification\Jobs\SendWhatsAppMessage;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Notification\Support\NotificationCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'notifications'])]
#[Title('Notifikasi')]
class Templates extends Component
{
    use WithPagination;

    #[Url(except: 'template')]
    public string $tab = 'template';

    #[Url(as: 'status', except: '')]
    public string $logStatus = '';

    #[Locked]
    public ?string $editing = null;

    public string $body = '';

    public function selectTab(string $tab): void
    {
        $this->tab = $tab === 'log' ? 'log' : 'template';
        $this->resetPage();
    }

    public function updatingLogStatus(): void
    {
        $this->resetPage();
    }

    public function edit(string $eventKey): void
    {
        abort_unless(isset(NotificationCatalog::EVENTS[$eventKey]), 404);

        $this->editing = $eventKey;
        $this->body = NotificationTemplate::query()->where('event_key', $eventKey)->where('channel', 'whatsapp')->value('body') ?? NotificationCatalog::EVENTS[$eventKey]['body'];
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'edit-template');
    }

    public function insertVariable(string $variable): void
    {
        abort_unless($this->editing && in_array($variable, NotificationCatalog::EVENTS[$this->editing]['variables'], true), 404);

        $this->body = rtrim($this->body).' {'.$variable.'}';
    }

    public function save(): void
    {
        abort_if($this->editing === null, 404);
        $this->resetErrorBag();

        $this->validate(['body' => 'required|string|max:2000'], [], ['body' => 'isi pesan']);

        $unknown = NotificationCatalog::unknownVariables($this->editing, $this->body);

        if ($unknown !== []) {
            $this->addError('body', 'Variabel tidak dikenal: {'.implode('}, {', $unknown).'}.');

            return;
        }

        NotificationTemplate::query()->updateOrCreate(['event_key' => $this->editing, 'channel' => 'whatsapp'], ['body' => $this->body]);

        $this->dispatch('close-modal', 'edit-template');
        $this->dispatch('toast', message: 'Template tersimpan.', tone: 'success');
    }

    public function restoreDefault(): void
    {
        abort_if($this->editing === null, 404);

        $this->body = NotificationCatalog::EVENTS[$this->editing]['body'];
    }

    public function toggle(string $eventKey): void
    {
        abort_unless(isset(NotificationCatalog::EVENTS[$eventKey]), 404);

        $template = NotificationTemplate::query()->firstOrNew(['event_key' => $eventKey, 'channel' => 'whatsapp'], ['body' => NotificationCatalog::EVENTS[$eventKey]['body']]);
        $template->is_enabled = ! ($template->exists ? $template->is_enabled : true);
        $template->save();
    }

    public function retry(string $uuid): void
    {
        $log = NotificationLog::query()->where('uuid', $uuid)->where('status', 'failed')->firstOrFail();
        $log->update(['status' => 'queued', 'attempts' => 0, 'error' => null]);

        SendWhatsAppMessage::dispatch($log)->onQueue('notifications');
        $this->dispatch('toast', message: 'Pesan dimasukkan ulang ke antrean.', tone: 'success');
    }

    /**
     * @return array<string, string>
     */
    private function sampleValues(): array
    {
        return [
            'nama_wali' => 'Bapak Contoh', 'nama_santri' => 'Santri Contoh', 'periode' => 'November 2026', 'total' => 'Rp 625.000', 'sisa' => 'Rp 625.000',
            'jatuh_tempo' => '10 November 2026', 'link' => url('/wali'), 'nominal' => 'Rp 625.000', 'metode' => 'transfer', 'alasan' => 'nominal belum sesuai',
            'judul' => 'Judul pengumuman', 'isi' => 'Isi pengumuman.', 'saldo' => 'Rp 75.000', 'merchant' => 'Warung Contoh', 'produk' => 'Roti', 'status' => 'Disetujui, belum dibayar',
        ];
    }

    public function render(): View
    {
        $templates = NotificationTemplate::query()->where('channel', 'whatsapp')->get()->keyBy('event_key');

        return view('notification.templates', [
            'events' => NotificationCatalog::EVENTS,
            'templates' => $templates,
            'preview' => NotificationCatalog::render($this->body, $this->sampleValues()),
            'logs' => $this->tab === 'log'
                ? NotificationLog::query()->when($this->logStatus !== '', fn ($query) => $query->where('status', $this->logStatus))->latest('id')->paginate(25)
                : null,
        ]);
    }
}
