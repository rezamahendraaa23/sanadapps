<?php

namespace App\Modules\Core\Livewire\Announcements;

use App\Modules\Core\Models\Announcement;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Masjid\Actions\ManageContent;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Notification\Actions\SendAnnouncement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'announcements'])]
#[Title('Pengumuman')]
class AnnouncementIndex extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editingId = null;

    public string $title = '';

    public string $body = '';

    public string $audience = 'all';

    public string $audience_id = '';

    public string $publish_at = '';

    public bool $send_wa = false;

    public bool $show_on_tv = false;

    public function create(): void
    {
        $this->reset('editingId', 'title', 'body', 'audience', 'audience_id', 'publish_at', 'send_wa', 'show_on_tv');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-pengumuman');
    }

    public function edit(string $uuid): void
    {
        $announcement = Announcement::query()->where('uuid', $uuid)->firstOrFail();
        $this->editingId = $announcement->id;
        $this->fill([
            'title' => $announcement->title,
            'body' => $announcement->body,
            'audience' => $announcement->audience,
            'audience_id' => (string) $announcement->audience_id,
            'send_wa' => (bool) $announcement->send_wa,
            'show_on_tv' => DisplayContent::query()->where('announcement_id', $announcement->id)->whereNot('status', 'archived')->exists(),
            'publish_at' => (string) $announcement->publish_at?->setTimezone(config('sanad.display_timezone'))->format('Y-m-d\TH:i'),
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-pengumuman');
    }

    public function save(): void
    {
        $this->resetErrorBag();
        $this->validate([
            'title' => 'required|string|max:150',
            'body' => 'required|string|max:5000',
            'audience' => 'required|in:all,classroom,dormitory',
            'audience_id' => 'required_unless:audience,all|nullable|integer',
            'publish_at' => 'nullable|date',
        ], ['audience_id.required_unless' => 'Pilih penerimanya.'], ['title' => 'judul', 'body' => 'isi', 'audience' => 'penerima', 'publish_at' => 'jadwal tayang']);

        $data = [
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,
            'audience_id' => $this->audience === 'all' ? null : (int) $this->audience_id,
            'publish_at' => $this->publish_at !== ''
                ? CarbonImmutable::parse($this->publish_at, config('sanad.display_timezone'))->utc()
                : now(),
            'send_wa' => $this->send_wa,
        ];

        if ($this->editingId) {
            $announcement = Announcement::query()->findOrFail($this->editingId);
            $announcement->update($data);
        } else {
            $announcement = Announcement::create([...$data, 'created_by' => auth()->id()]);

            if ($this->send_wa && ! $announcement->publish_at->isFuture()) {
                app(SendAnnouncement::class)->handle($announcement);
            }
        }

        // Ditandai tayang di TV secara sadar; pengumuman berpenerima kelas atau asrama tidak otomatis tayang (PRD Masjid 8.3).
        if (app(ModuleRegistry::class)->enabled('masjid_display') && auth()->user()->can('masjid.content')) {
            app(ManageContent::class)->syncAnnouncement($announcement, $this->show_on_tv, auth()->user());
        }

        $this->dispatch('close-modal', 'form-pengumuman');
        $this->dispatch('toast', message: 'Pengumuman tersimpan.', tone: 'success');
    }

    public function delete(string $uuid): void
    {
        Announcement::query()->where('uuid', $uuid)->firstOrFail()->delete();
        $this->dispatch('toast', message: 'Pengumuman dihapus.', tone: 'success');
    }

    public function render(): View
    {
        return view('core.announcements.index', [
            'announcements' => Announcement::query()->latest('publish_at')->paginate(20),
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
            'dormitories' => Dormitory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'waEnabled' => app(ModuleRegistry::class)->enabled('notification_wa'),
            'tvEnabled' => app(ModuleRegistry::class)->enabled('masjid_display') && auth()->user()->can('masjid.content'),
        ]);
    }
}
