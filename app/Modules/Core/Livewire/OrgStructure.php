<?php

namespace App\Modules\Core\Livewire;

use App\Modules\Core\Models\OrgChart;
use App\Modules\Core\Models\OrgPosition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Struktur pesantren: menambah jabatan selalu dari kotak atasannya, jadi hubungannya tersambung sendiri.
 */
#[Layout('components.layouts.staff', ['active' => 'org'])]
#[Title('Struktur Pesantren')]
class OrgStructure extends Component
{
    use WithFileUploads;

    #[Url(as: 'bagan', except: 'yayasan')]
    public string $chartKey = 'yayasan';

    #[Locked]
    public ?string $editing = null;

    #[Locked]
    public ?string $parent = null;

    public string $title = '';

    public string $person_name = '';

    public string $note = '';

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public bool $removePhoto = false;

    public function mount(): void
    {
        OrgChart::ensureDefaults();
        abort_unless(isset(OrgChart::DEFAULTS[$this->chartKey]), 404);
    }

    private function chart(): OrgChart
    {
        return OrgChart::query()->where('key', $this->chartKey)->firstOrFail();
    }

    public function selectChart(string $key): void
    {
        abort_unless(isset(OrgChart::DEFAULTS[$key]), 404);
        $this->chartKey = $key;
    }

    /**
     * Kotak baru di bawah atasan tertentu; tanpa atasan berarti puncak bagan.
     */
    public function add(?string $parentUuid = null): void
    {
        $this->parent = $parentUuid ? $this->chart()->positions()->where('uuid', $parentUuid)->firstOrFail()->uuid : null;
        $this->reset('editing', 'title', 'person_name', 'note', 'photo', 'removePhoto');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-jabatan');
    }

    public function edit(string $uuid): void
    {
        $position = $this->chart()->positions()->where('uuid', $uuid)->firstOrFail();
        $this->editing = $position->uuid;
        $this->parent = $position->parent?->uuid;
        $this->fill(['title' => $position->title, 'person_name' => (string) $position->person_name, 'note' => (string) $position->note, 'photo' => null, 'removePhoto' => false]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-jabatan');
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:80',
            'person_name' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:120',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ], [], ['title' => 'jabatan', 'person_name' => 'nama', 'note' => 'keterangan', 'photo' => 'foto']);

        $chart = $this->chart();
        $parentId = $this->parent ? $chart->positions()->where('uuid', $this->parent)->value('id') : null;
        $position = $this->editing ? $chart->positions()->where('uuid', $this->editing)->firstOrFail() : new OrgPosition(['chart_id' => $chart->id, 'parent_id' => $parentId, 'sort_order' => (int) $chart->positions()->where('parent_id', $parentId)->max('sort_order') + 1]);

        $values = ['title' => trim($this->title), 'person_name' => trim($this->person_name) ?: null, 'note' => trim($this->note) ?: null];
        if ($this->photo || $this->removePhoto) {
            if ($position->photo_path) {
                Storage::disk('local')->delete($position->photo_path);
            }
            $values['photo_path'] = $this->photo?->store('org-photos', 'local');
        }

        $position->fill($values)->save();
        $this->dispatch('close-modal', 'form-jabatan');
        $this->dispatch('toast', message: $this->editing ? 'Jabatan diperbarui.' : 'Jabatan ditambahkan.', tone: 'success');
    }

    /**
     * Menghapus satu kotak; bawahannya naik ke atasan kotak itu, jadi bagan tidak putus.
     */
    public function remove(string $uuid): void
    {
        $position = $this->chart()->positions()->where('uuid', $uuid)->firstOrFail();

        DB::transaction(function () use ($position): void {
            OrgPosition::query()->where('parent_id', $position->id)->update(['parent_id' => $position->parent_id]);
            if ($position->photo_path) {
                Storage::disk('local')->delete($position->photo_path);
            }
            $position->delete();
        });

        $this->dispatch('toast', message: 'Jabatan dihapus. Bawahannya pindah ke atasan.', tone: 'success');
    }

    public function move(string $uuid, int $direction): void
    {
        $position = $this->chart()->positions()->where('uuid', $uuid)->firstOrFail();
        $siblings = $this->chart()->positions()->where('parent_id', $position->parent_id)->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $siblings->search(fn (OrgPosition $row) => $row->id === $position->id);
        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($siblings[$target])) {
            return;
        }

        DB::transaction(function () use ($siblings, $index, $target): void {
            $order = $siblings->all();
            [$order[$index], $order[$target]] = [$order[$target], $order[$index]];
            foreach (array_values($order) as $sort => $row) {
                $row->update(['sort_order' => $sort]);
            }
        });
    }

    public function toggleTv(): void
    {
        $chart = $this->chart();
        $chart->update(['show_on_tv' => ! $chart->show_on_tv]);
        $this->dispatch('toast', message: $chart->show_on_tv ? 'Bagan ini akan tampil bergiliran di layar TV.' : 'Bagan ini tidak tampil di TV.', tone: 'success');
    }

    public function render(): View
    {
        $chart = $this->chart();

        return view('core.org-structure', [
            'chart' => $chart,
            'charts' => OrgChart::query()->orderBy('id')->get(),
            'tree' => $chart->tree(),
            'parentPosition' => $this->parent ? $chart->positions()->where('uuid', $this->parent)->first() : null,
            'editingPosition' => $this->editing ? $chart->positions()->where('uuid', $this->editing)->first() : null,
        ]);
    }
}
