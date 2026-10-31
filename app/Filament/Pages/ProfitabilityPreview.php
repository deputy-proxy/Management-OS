<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\ProfitabilityCalculationRun;
use App\Services\ProfitabilityCalculationService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ProfitabilityPreview extends Page
{
    use HasHexaLite;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Profitability Preview';

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?string $cluster = FinanceCluster::class;

    protected string $view = 'filament.clusters.finance.pages.profitability-preview';

    public string $periodStart = '';

    public string $periodEnd = '';

    public ?int $selectedRunId = null;

    public function mount(): void
    {
        $today = CarbonImmutable::now('Europe/Bucharest');
        $this->periodStart = $today->startOfMonth()->toDateString();
        $this->periodEnd = $today->toDateString();
    }

    public function defineGates(): array
    {
        return [
            'finance-profitability.view' => __('Allows viewing provisional profitability calculation runs'),
            'finance-profitability.calculate' => __('Allows creating provisional profitability calculation runs'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('finance-profitability.view');
    }

    public function calculate(): void
    {
        abort_unless(hexa()->can('finance-profitability.calculate'), 403);

        try {
            $run = app(ProfitabilityCalculationService::class)->calculate($this->periodStart, $this->periodEnd);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->selectedRunId = $run->getKey();
        Notification::make()
            ->title('Provisional calculation saved')
            ->body('The run is immutable and remains explicitly provisional.')
            ->success()
            ->send();
    }

    public function selectRun(int $runId): void
    {
        abort_unless(hexa()->can('finance-profitability.view'), 403);
        abort_unless(ProfitabilityCalculationRun::query()->whereKey($runId)->exists(), 404);
        $this->selectedRunId = $runId;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $runs = ProfitabilityCalculationRun::query()
            ->with('results.product')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $selectedRun = $this->selectedRunId
            ? $runs->firstWhere('id', $this->selectedRunId)
            : $runs->first();

        return [
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'canCalculate' => hexa()->can('finance-profitability.calculate'),
        ];
    }
}
