<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Budget;
use App\Models\BudgetActualReportRun;
use App\Models\BudgetVersion;
use App\Services\BudgetActualsComparisonService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class BudgetActualsReport extends Page
{
    use HasHexaLite;

    protected static ?string $slug = 'budget-actuals-report';

    protected static ?string $navigationLabel = 'Budget vs Actual';

    protected static string|UnitEnum|null $navigationGroup = 'Budget Management';

    protected static ?string $cluster = FinanceCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.budget-actuals-report';

    public ?int $budgetId = null;

    public ?int $selectedVersionId = null;

    public string $periodStart = '';

    public string $periodEnd = '';

    public string $dimensionKey = '';

    public string $dimensionValue = '';

    /** @var array<string, mixed>|null */
    public ?array $report = null;

    public ?int $selectedReportRunId = null;

    public function openSnapshot(int $reportRunId): void
    {
        $version = $this->selectedVersion();
        abort_unless($version instanceof BudgetVersion, 404);

        $snapshot = BudgetActualReportRun::query()
            ->where('budget_version_id', $version->getKey())
            ->findOrFail($reportRunId);

        $this->report = $snapshot->report_snapshot;
        $this->report['report_run_id'] = (int) $snapshot->getKey();
        $this->selectedReportRunId = (int) $snapshot->getKey();
    }

    public function defineGates(): array
    {
        return [
            'budget.actuals.view' => __('Allows viewing budget-versus-actual cash movement comparisons'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('budget.actuals.view');
    }

    public function mount(): void
    {
        $this->budgetId = request()->integer('budget') ?: null;
        $this->selectedVersionId = request()->integer('version') ?: null;

        $version = $this->selectedVersion();
        if ($version) {
            $this->periodStart = $version->period_start->toDateString();
            $this->periodEnd = $version->period_end->toDateString();
        }
    }

    public function updatedSelectedVersionId(): void
    {
        $version = $this->selectedVersion();
        if (! $version) {
            $this->report = null;

            return;
        }

        $this->periodStart = $version->period_start->toDateString();
        $this->periodEnd = $version->period_end->toDateString();
        $this->dimensionKey = '';
        $this->dimensionValue = '';
        $this->report = null;
    }

    public function generateReport(): void
    {
        abort_unless(hexa()->can('budget.actuals.view'), 403);

        $version = $this->selectedVersion();
        abort_unless($version instanceof BudgetVersion, 404);

        $dimensions = $this->dimensionKey !== '' && $this->dimensionValue !== ''
            ? [$this->dimensionKey => $this->dimensionValue]
            : [];

        try {
            $this->report = app(BudgetActualsComparisonService::class)->compare(
                $version,
                $this->periodStart,
                $this->periodEnd,
                $version->currency_code,
                $dimensions,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        Notification::make()
            ->title('Budget comparison generated')
            ->body('Cash movement actuals are provisional and are not accrual or recognized revenue.')
            ->warning()
            ->send();
    }

    public function transactionUrl(int $transactionId): ?string
    {
        return hexa()->can('transaction.index')
            ? TransactionResource::getUrl('view', ['record' => $transactionId])
            : null;
    }

    public function budgetLineUrl(int $lineItemId): ?string
    {
        $version = $this->selectedVersion();
        if (! $version || ! hexa()->can('budget.index')) {
            return null;
        }

        return BudgetResource::getUrl('index', ['tableSearch' => $version->budget?->name]);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $budget = $this->budgetId
            ? Budget::query()->with('versions.lineItems')->find($this->budgetId)
            : null;

        $versions = $budget !== null
            ? $budget->versions
            : BudgetVersion::query()->with('budget')->orderByDesc('id')->limit(100)->get();

        return [
            'budget' => $budget,
            'versions' => $versions,
            'selectedVersion' => $this->selectedVersion(),
            'reportRuns' => $this->selectedVersion()
                ? BudgetActualReportRun::query()->where('budget_version_id', $this->selectedVersion()->getKey())->latest('created_at')->limit(20)->get()
                : collect(),
        ];
    }

    private function selectedVersion(): ?BudgetVersion
    {
        if (! $this->selectedVersionId) {
            return null;
        }

        $query = BudgetVersion::query()->with('budget', 'lineItems');
        if ($this->budgetId) {
            $query->where('budget_id', $this->budgetId);
        }

        return $query->find($this->selectedVersionId);
    }
}