<?php

namespace Tests\Feature;

use App\Filament\Pages\BudgetActualsReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetActualsReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_budget_actuals_report_requires_its_dedicated_permission(): void
    {
        $page = new BudgetActualsReport;
        $this->assertSame([
            'budget.actuals.view' => __('Allows viewing budget-versus-actual cash movement comparisons'),
        ], $page->defineGates());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create())
            ->get(BudgetActualsReport::getUrl())
            ->assertForbidden();
    }

    public function test_budget_actuals_report_is_available_to_users_with_its_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Budget actuals viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => ['budget.actuals.view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(BudgetActualsReport::getUrl())
            ->assertSuccessful()
            ->assertSee('Provisional cash-movement comparison.')
            ->assertSee('Budget version')
            ->assertSee('No budget versions are available. Create a budget version before generating a comparison.');
    }
}
