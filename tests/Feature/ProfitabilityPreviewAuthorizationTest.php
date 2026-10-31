<?php

namespace Tests\Feature;

use App\Filament\Pages\ProfitabilityPreview;
use App\Models\Product;
use App\Models\ProfitabilityCalculationResult;
use App\Models\ProfitabilityCalculationRun;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfitabilityPreviewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profitability_preview_requires_its_view_permission(): void
    {
        $page = new ProfitabilityPreview;
        $this->assertSame([
            'finance-profitability.view' => __('Allows viewing provisional profitability calculation runs'),
            'finance-profitability.calculate' => __('Allows creating provisional profitability calculation runs'),
            'finance-profitability.customer-view' => __('Allows viewing customer revenue attribution in provisional profitability reports'),
        ], $page->defineGates());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Profitability viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => ['finance-profitability.view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(ProfitabilityPreview::getUrl())
            ->assertSuccessful()
            ->assertSee('Provisional operational reporting only.')
            ->assertDontSee('Create provisional run');
    }

    public function test_customer_drilldown_requires_both_customer_permissions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $product = Product::factory()->create([
            'productable_type' => 'course',
            'productable_id' => 1,
        ]);
        $run = ProfitabilityCalculationRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => 'provisional',
            'formula_version' => 'operational-contribution-v1',
            'assumptions' => [],
            'exceptions' => [],
        ]);
        ProfitabilityCalculationResult::query()->create([
            'profitability_calculation_run_id' => $run->getKey(),
            'product_id' => $product->getKey(),
            'currency_code' => 'RON',
            'revenue_amount' => '10.00',
            'direct_cost_amount' => '1.00',
            'overhead_cost_amount' => '1.00',
            'provisional_profit_amount' => '8.00',
            'provisional_margin_percent' => '80.00',
            'result_status' => 'provisional_complete',
            'source_snapshot' => [
                'revenue_ids' => [],
                'cost_snapshot_ids' => [],
                'cost_resource_ids' => [],
                'cost_rule_versions' => [],
            ],
        ]);

        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Profitability customer viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => ['finance-profitability.view', 'finance-profitability.customer-view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(ProfitabilityPreview::getUrl())
            ->assertSuccessful()
            ->assertSee('Customer drill-down requires both the profitability customer-view permission and access to customer invoices.')
            ->assertDontSee('Customer revenue drill-down');
    }

    public function test_calculation_action_is_only_rendered_with_its_separate_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Profitability calculator '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => ['finance-profitability.view', 'finance-profitability.calculate']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(ProfitabilityPreview::getUrl())
            ->assertSuccessful()
            ->assertSee('Create provisional run');
    }
}
