<?php

namespace App\Modules\Core\Livewire\Staff;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Student;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Letters\Models\Letter;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Satu kotak untuk mencari apa saja, seperti Odoo: menu, santri, wali, tagihan, dan surat.
 * Hanya yang boleh dilihat pengguna yang muncul.
 */
class GlobalSearch extends Component
{
    public string $query = '';

    /** Kata lain untuk menu, agar "pos" menemukan Kasir dan "spp" menemukan Tagihan. */
    private const KEYWORDS = [
        'pos' => 'kasir jual belanja pos', 'merchants' => 'mart warung kantin koperasi toko produk stok pembelian promo pemasok',
        'invoices' => 'tagihan spp bayar iuran', 'payments' => 'pembayaran kwitansi transfer', 'payments-cash' => 'terima bayar tunai loket spp uang makan iuran kasir keuangan', 'cash' => 'kas uang keluar masuk',
        'cards' => 'kartu santri cetak qr', 'wallet' => 'dompet saldo topup', 'leaves' => 'izin pulang', 'gate' => 'gerbang satpam keluar masuk',
        'attendance' => 'absen kehadiran', 'grades' => 'nilai rapor', 'curriculum' => 'kurikulum fase sertifikat syahadah',
        'exams' => 'ujian soal cbt', 'tahfidz' => 'hafalan setoran quran', 'letters' => 'surat kop nomor', 'masjid' => 'sholat adzan jadwal',
        'masjid-screens' => 'tv layar display', 'masjid-contents' => 'pengumuman tv konten', 'devices' => 'reader alat pemindai',
        'students' => 'santri murid', 'guardians' => 'wali orang tua', 'ppdb' => 'pendaftaran santri baru',
    ];

    /**
     * @return Collection<int, array{label: string, group: string, icon: string, url: string}>
     */
    private function menus(string $term): Collection
    {
        $needle = Str::lower($term);

        return collect(app(Navigation::class)->staff())
            ->flatMap(fn (array $group) => collect($group['items'])->map(fn (array $item) => [...$item, 'group' => $group['label']]))
            ->filter(fn (array $item) => Str::contains(Str::lower($item['label'].' '.$item['group'].' '.(self::KEYWORDS[$item['key']] ?? '')), $needle))
            ->take(6)
            ->map(fn (array $item) => ['label' => $item['label'], 'group' => $item['group'], 'icon' => $item['icon'], 'url' => $item['url']])
            ->values();
    }

    public function render(): View
    {
        $user = auth()->user();
        $term = trim($this->query);
        $searched = mb_strlen($term) >= 2;
        $like = '%'.addcslashes($term, '%_').'%';

        $results = $searched && $user->can('viewAny', Student::class)
            ? Student::query()
                ->visibleTo($user)
                ->with('classroom:id,name')
                ->where(fn ($inner) => $inner
                    ->where('name', 'ilike', $like)
                    ->orWhere('nis', 'like', addcslashes($term, '%_').'%'))
                ->orderBy('name')
                ->limit(6)
                ->get(['id', 'uuid', 'name', 'nis', 'classroom_id'])
            : collect();

        $guardians = $searched && $user->can('viewAny', Guardian::class)
            ? Guardian::query()->where(fn ($inner) => $inner->where('name', 'ilike', $like)
                ->when(strlen($digits = (string) preg_replace('/\D/', '', $term)) >= 4, fn ($query) => $query->orWhere('phone_wa', 'like', '%'.$digits.'%')))
                ->orderBy('name')->limit(4)->get(['id', 'uuid', 'name', 'phone_wa'])
            : collect();

        $invoices = $searched && $user->can('finance.view')
            ? Invoice::query()->with('student:id,name')->where('invoice_no', 'ilike', $like)->latest('id')->limit(4)->get()
            : collect();

        $letters = $searched && $user->can('letters.manage') && Route::has('staff.letters.index')
            ? Letter::query()->where(fn ($inner) => $inner->where('letter_no', 'ilike', $like)->orWhere('subject', 'ilike', $like))->latest('id')->limit(4)->get(['id', 'uuid', 'letter_no', 'subject'])
            : collect();

        return view('core.staff.global-search', [
            'menus' => $searched ? $this->menus($term) : collect(),
            'results' => $results,
            'guardians' => $guardians,
            'invoices' => $invoices,
            'letters' => $letters,
            'searched' => $searched,
        ]);
    }
}
