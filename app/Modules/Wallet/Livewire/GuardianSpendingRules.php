<?php

namespace App\Modules\Wallet\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\ProductCategory;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletMerchantLimit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'wallet', 'title' => 'Aturan Belanja'])]
#[Title('Aturan Belanja')]
class GuardianSpendingRules extends Component
{
    #[Url(as: 'anak', except: '')]
    public string $childUuid = '';

    public string $daily_limit = '';

    /** @var array<string, array{limit: string, blocked: bool}> */
    public array $merchantRules = [];

    /** @var array<string, array{limit: string, blocked: bool}> */
    public array $categoryRules = [];

    public function mount(): void
    {
        $wallet = $this->wallet();
        $this->daily_limit = (string) $wallet->daily_limit;
        $saved = WalletMerchantLimit::query()->where('wallet_id', $wallet->id)->get();

        foreach (Merchant::query()->where('is_active', true)->orderBy('name')->get() as $merchant) {
            $rule = $saved->firstWhere('merchant_id', $merchant->id);
            $this->merchantRules[$merchant->uuid] = ['limit' => (string) $rule?->daily_limit, 'blocked' => (bool) $rule?->is_blocked];
        }

        foreach ($this->categories() as $category) {
            $rule = $saved->firstWhere('category_id', $category->id);
            $this->categoryRules[$category->uuid] = ['limit' => (string) $rule?->daily_limit, 'blocked' => (bool) $rule?->is_blocked];
        }
    }

    private function child(): Student
    {
        $children = auth()->user()->guardian?->students()->orderBy('name')->get() ?? collect();
        $child = $children->firstWhere('uuid', $this->childUuid) ?? $children->first();
        abort_if($child === null, 404);

        return $child;
    }

    private function wallet(): Wallet
    {
        return Wallet::forStudent($this->child());
    }

    private function categories()
    {
        return ProductCategory::query()->where('is_restricted', true)->orderBy('name')->get();
    }

    private function amount(string $value): ?int
    {
        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' ? null : (int) $digits;
    }

    public function save(): void
    {
        $wallet = $this->wallet();

        DB::transaction(function () use ($wallet): void {
            $wallet->update(['daily_limit' => $this->amount($this->daily_limit)]);

            foreach (Merchant::query()->whereIn('uuid', array_keys($this->merchantRules))->get() as $merchant) {
                $this->storeRule($wallet, ['merchant_id' => $merchant->id], $this->merchantRules[$merchant->uuid]);
            }

            foreach ($this->categories()->whereIn('uuid', array_keys($this->categoryRules)) as $category) {
                $this->storeRule($wallet, ['category_id' => $category->id], $this->categoryRules[$category->uuid]);
            }
        });

        $this->dispatch('toast', message: 'Aturan belanja tersimpan dan langsung berlaku.', tone: 'success');
    }

    /**
     * @param  array<string, int>  $target
     * @param  array{limit: string, blocked: bool}  $rule
     */
    private function storeRule(Wallet $wallet, array $target, array $rule): void
    {
        $limit = $this->amount($rule['limit']);
        $blocked = (bool) $rule['blocked'];
        $query = WalletMerchantLimit::query()->where('wallet_id', $wallet->id)->where($target);

        if ($limit === null && ! $blocked) {
            $query->first()?->delete();

            return;
        }

        WalletMerchantLimit::query()->updateOrCreate(['wallet_id' => $wallet->id, ...$target], ['daily_limit' => $limit, 'is_blocked' => $blocked]);
    }

    public function render(): View
    {
        $child = $this->child();
        $wallet = Wallet::forStudent($child);
        $start = now(config('sanad.display_timezone'))->startOfMonth()->utc();

        return view('wallet.spending-rules', [
            'child' => $child,
            'merchants' => Merchant::query()->whereIn('uuid', array_keys($this->merchantRules))->orderBy('name')->get(),
            'categories' => $this->categories()->whereIn('uuid', array_keys($this->categoryRules)),
            'monthly' => $wallet->transactions()->where('type', 'spend')->whereNotNull('merchant_id')->where('created_at', '>=', $start)
                ->selectRaw('merchant_id, SUM(amount) AS total, COUNT(*) AS orders')->groupBy('merchant_id')->with('merchant:id,name')->get(),
        ]);
    }
}
