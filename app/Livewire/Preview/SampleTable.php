<?php

namespace App\Livewire\Preview;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Tabel contoh untuk menguji komponen tabel beserta keadaan terisi, kosong, dan gagal.
 */
class SampleTable extends Component
{
    public string $search = '';

    public string $sort = 'name';

    public string $direction = 'asc';

    public bool $failed = false;

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['name', 'amount'], true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function simulateFailure(): void
    {
        $this->failed = true;
    }

    public function retry(): void
    {
        $this->failed = false;
        $this->dispatch('toast', message: 'Data contoh berhasil dimuat ulang.', tone: 'success');
    }

    public function render(): View
    {
        return view('livewire.preview.sample-table', ['rows' => $this->rows()]);
    }

    /**
     * @return Collection<int, array{name: string, code: string, amount: int, status: string}>
     */
    private function rows(): Collection
    {
        $statuses = ['paid', 'unpaid', 'review', 'overdue', 'partial', 'paid'];

        $rows = collect(range(1, 6))->map(fn (int $number): array => [
            'name' => sprintf('Santri Contoh %02d', $number),
            'code' => sprintf('CONTOH-%03d', $number),
            'amount' => 350000 + ($number * 25000),
            'status' => $statuses[$number - 1],
        ]);

        return $rows
            ->filter(fn (array $row): bool => $this->search === ''
                || str_contains(mb_strtolower($row['name'].' '.$row['code']), mb_strtolower(trim($this->search))))
            ->sortBy($this->sort, descending: $this->direction === 'desc')
            ->values();
    }
}
