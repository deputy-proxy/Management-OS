<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\CustomerLead;
use App\Models\CustomerUnitEconomicsRun;
use App\Services\CustomerUnitEconomicsService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class CustomerUnitEconomicsReport extends Page
{
    use HasHexaLite;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'CAC & Lifetime Value';

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?string $cluster = FinanceCluster::class;

    protected string $view = 'filament.clusters.finance.pages.customer-unit-economics-report';

    public string $periodStart = '';

    public string $periodEnd = '';

    public ?int $selectedRunId = null;

    public string $costIncurredOn = '';

    public ?string $costSourceCategory = null;

    public string $costCampaignName = '';

    public string $costAmount = '';

    public string $costCurrency = 'RON';

    public string $costSourceReference = '';

    public string $costEvidenceReference = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('Europe/Bucharest');
        $this->periodStart = $today->startOfMonth()->toDateString();
        $this->periodEnd = $today->toDateString();
        $this->costIncurredOn = $today->toDateString();
    }

    /** @return array<string, string> */
    public function defineGates(): array
    {
        return [
            'finance-unit-economics.view' => __('Allows viewing customer acquisition cost and lifetime-value reports'),
            'finance-unit-economics.calculate' => __('Allows creating immutable customer unit-economics runs'),
            'finance-unit-economics.manage-costs' => __('Allows recording evidence-backed acquisition costs'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('finance-unit-economics.view');
    }

    public function recordCost(): void
    {
        abort_unless(hexa()->can('finance-unit-economics.manage-costs'), 403);

        try {
            app(CustomerUnitEconomicsService::class)->recordAcquisitionCost([
                'incurred_on' => $this->costIncurredOn,
                'source_category' => $this->costSourceCategory,
                'campaign_name' => $this->costCampaignName ?: null,
                'amount_decimal' => $this->costAmount,
                'currency_code' => $this->costCurrency,
                'source_reference' => $this->costSourceReference,
                'evidence_reference' => $this->costEvidenceReference,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $this->costAmount = '';
        $this->costCampaignName = '';
        $this->costSourceReference = '';
        $this->costEvidenceReference = '';

        Notification::make()
            ->title('Acquisition cost recorded')
            ->body('The evidence entry is append-only. Corrections must be recorded as new entries.')
            ->success()
            ->send();
    }

    public function calculate(): void
    {
        abort_unless(hexa()->can('finance-unit-economics.calculate'), 403);

        $run = app(CustomerUnitEconomicsService::class)->calculate($this->periodStart, $this->periodEnd);
        $this->selectedRunId = (int) $run->getKey();

        Notification::make()
            ->title('CAC and LTV calculation saved')
            ->body('The run stores its policy version and source references and cannot be edited.')
            ->success()
            ->send();
    }

    public function selectRun(int $runId): void
    {
        abort_unless(hexa()->can('finance-unit-economics.view'), 403);
        abort_unless(CustomerUnitEconomicsRun::query()->whereKey($runId)->exists(), 404);

        $this->selectedRunId = $runId;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $runs = CustomerUnitEconomicsRun::query()
            ->with('results')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
        $selectedRun = $this->selectedRunId
            ? $runs->firstWhere('id', $this->selectedRunId)
            : $runs->first();

        return [
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'canCalculate' => hexa()->can('finance-unit-economics.calculate'),
            'canManageCosts' => hexa()->can('finance-unit-economics.manage-costs'),
            'sourceCategories' => CustomerLead::sourceCategories(),
        ];
    }
}
