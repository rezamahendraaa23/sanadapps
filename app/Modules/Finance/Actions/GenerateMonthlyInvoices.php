<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Core\Models\Module;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Models\FeeType;
use App\Support\SchoolDate;
use Carbon\CarbonImmutable;

class GenerateMonthlyInvoices
{
    public const DEFAULT_DAYS_BEFORE = 7;

    public function __construct(private GenerateInvoices $generator, private ModuleRegistry $modules) {}

    /**
     * Job harian: jenis bulanan ditagih mulai H-N sebelum jatuh tempo, N bisa diatur (PRD Sistem 8.3).
     *
     * @return array<string, mixed>|null null bila tidak ada yang waktunya ditagih.
     */
    public function handle(): ?array
    {
        if (! $this->modules->enabled('finance')) {
            return null;
        }

        $today = SchoolDate::today();
        $period = $today->startOfMonth();
        $days = (int) (Module::query()->where('module_key', 'finance')->value('settings')['generate_days_before'] ?? self::DEFAULT_DAYS_BEFORE);

        $due = FeeType::query()
            ->where('is_active', true)
            ->where('category', 'monthly')
            ->get()
            ->filter(fn (FeeType $type) => $today->greaterThanOrEqualTo(CarbonImmutable::parse(CalculateCharges::dueDate($type, $period))->subDays($days)))
            ->pluck('id')
            ->all();

        return $due === [] ? null : $this->generator->run($period, $due);
    }
}
