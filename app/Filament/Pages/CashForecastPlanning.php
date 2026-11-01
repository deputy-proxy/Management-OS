<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\CashForecastRun;
use App\Models\CashForecastScenario;
use App\Services\CashForecastService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Hexters\HexaLite\HasHexaLite;
use UnitEnum;

class CashForecastPlanning extends Page
{
    use HasHexaLite;

    protected static ?string $slug = 'cash-forecast-planning';

    protected static ?string $navigationLabel = 'Cash Forecast & Planning';

    protected static string|UnitEnum|null $navigationGroup = 'Overview';

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.pages.cash-forecast-planning';

    public ?int $selectedScenarioId = null;

    public string $forecastName = '';

    public string $scenarioName = 'Base case';

    public string $periodStart = '';

    public string $periodEnd = '';

    public string $currencyCode = 'RON';

    public string $openingBalance = '';

    public string $assumptions = '';

    /** @var array<int, array<string, string>> */
    public array $entries = [];

    /** @var array<string, mixed>|null */
    public ?array $report = null;

    public function defineGates(): array
    {
        return [
            'cash-forecast.view' => __('Allows viewing cash forecasts and planning scenarios'),
            'cash-forecast.create' => __('Allows creating cash forecasts'),
            'cash-forecast.update' => __('Allows editing drafts and creating forecast versions'),
            'cash-forecast.approve' => __('Allows approving forecast scenarios'),
        ];
    }

    public static function canAccess(): bool
    {
        return hexa()->can('cash-forecast.view');
    }

    public function mount(): void
    {
        $today = CarbonImmutable::now('Europe/Bucharest');
        $this->periodStart = $today->toDateString();
        $this->periodEnd = $today->addDays(90)->toDateString();
        $this->entries = [$this->emptyEntry()];
        $requested = request()->integer('scenario');
        if ($requested > 0) {
            $this->selectScenario($requested);
        }
    }

    public function updatedSelectedScenarioId(): void
    {
        if ($this->selectedScenarioId) {
            $this->selectScenario($this->selectedScenarioId);
        } else {
            $this->resetEditor();
        }
    }

    public function addEntry(): void
    {
        $this->entries[] = $this->emptyEntry();
    }

    public function removeEntry(int $index): void
    {
        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);
    }

    public function saveScenario(): void
    {
        $data = $this->payload();
        if ($this->selectedScenarioId) {
            $scenario = app(CashForecastService::class)->updateDraft(
                CashForecastScenario::query()->findOrFail($this->selectedScenarioId), $data,
            );
            $message = 'Draft scenario updated.';
        } else {
            $data['name'] = $this->forecastName;
            $scenario = app(CashForecastService::class)->create($data);
            $message = 'Cash forecast created.';
        }
        $this->selectScenario((int) $scenario->getKey());
        Notification::make()->title($message)->success()->send();
    }

    public function createVersion(): void
    {
        abort_unless($this->selectedScenarioId !== null, 404);
        $scenario = CashForecastScenario::query()->findOrFail($this->selectedScenarioId);
        $version = app(CashForecastService::class)->createVersion($scenario, $this->payload());
        $this->selectScenario((int) $version->getKey());
        Notification::make()->title('New draft version created')->success()->send();
    }

    public function approveScenario(): void
    {
        abort_unless($this->selectedScenarioId !== null, 404);
        $scenario = app(CashForecastService::class)->approve(CashForecastScenario::query()->findOrFail($this->selectedScenarioId));
        $this->selectScenario((int) $scenario->getKey());
        Notification::make()->title('Forecast scenario approved')->success()->send();
    }

    public function generateReport(): void
    {
        abort_unless(hexa()->can('cash-forecast.view'), 403);
        abort_unless($this->selectedScenarioId !== null, 404);
        $run = app(CashForecastService::class)->generateRun(
            CashForecastScenario::query()->findOrFail($this->selectedScenarioId),
        );
        $this->report = $run->report_snapshot;
    }

    public function loadRun(int $runId): void
    {
        abort_unless(hexa()->can('cash-forecast.view'), 403);
        abort_unless($this->selectedScenarioId !== null, 404);
        $run = CashForecastRun::query()
            ->where('cash_forecast_scenario_id', $this->selectedScenarioId)
            ->findOrFail($runId);
        $this->report = $run->report_snapshot;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $selectedScenario = $this->selectedScenarioId
            ? CashForecastScenario::query()->with('forecast')->find($this->selectedScenarioId)
            : null;

        return [
            'scenarios' => CashForecastScenario::query()->with('forecast')->orderByDesc('created_at')->limit(100)->get(),
            'selectedScenario' => $selectedScenario,
            'runs' => $selectedScenario?->runs()->limit(25)->get() ?? collect(),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'scenario_name' => $this->scenarioName,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'currency_code' => $this->currencyCode,
            'opening_balance' => $this->openingBalance,
            'assumptions' => $this->assumptions,
            'entries' => $this->entries,
        ];
    }

    private function selectScenario(int $id): void
    {
        $scenario = CashForecastScenario::query()->with('forecast', 'entries')->findOrFail($id);
        $this->selectedScenarioId = $id;
        $this->forecastName = $scenario->forecast->name;
        $this->scenarioName = $scenario->scenario_name;
        $this->periodStart = $scenario->period_start->toDateString();
        $this->periodEnd = $scenario->period_end->toDateString();
        $this->currencyCode = $scenario->currency_code;
        $this->openingBalance = (string) $scenario->opening_balance;
        $this->assumptions = (string) ($scenario->assumptions['notes'] ?? '');
        $this->entries = $scenario->entries->map(fn ($entry): array => [
            'direction' => $entry->direction,
            'description' => $entry->description,
            'expected_on' => $entry->expected_on->toDateString(),
            'amount' => (string) $entry->amount,
            'currency_code' => $entry->currency_code,
            'confidence' => $entry->confidence,
            'status' => $entry->status,
            'source_reference' => (string) ($entry->source_reference ?? ''),
        ])->all();
        if ($this->entries === []) {
            $this->entries = [$this->emptyEntry()];
        }
        $this->report = null;
    }

    private function resetEditor(): void
    {
        $this->forecastName = '';
        $this->scenarioName = 'Base case';
        $this->currencyCode = 'RON';
        $this->openingBalance = '';
        $this->assumptions = '';
        $this->entries = [$this->emptyEntry()];
        $this->report = null;
    }

    /** @return array<string, string> */
    private function emptyEntry(): array
    {
        return [
            'direction' => 'outflow', 'description' => '', 'expected_on' => $this->periodStart ?: CarbonImmutable::now('Europe/Bucharest')->toDateString(),
            'amount' => '', 'currency_code' => $this->currencyCode ?: 'RON', 'confidence' => 'medium', 'status' => 'assumed', 'source_reference' => '',
        ];
    }
}
