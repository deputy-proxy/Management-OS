<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomerUnitEconomicsReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerUnitEconomicsReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_economics_page_exposes_separate_view_calculate_and_cost_entry_gates(): void
    {
        $page = new CustomerUnitEconomicsReport;

        $this->assertSame([
            'finance-unit-economics.view' => __('Allows viewing customer acquisition cost and lifetime-value reports'),
            'finance-unit-economics.calculate' => __('Allows creating immutable customer unit-economics runs'),
            'finance-unit-economics.manage-costs' => __('Allows recording evidence-backed acquisition costs'),
            'finance-unit-economics.manage-retention' => __('Allows recording explicit customer retention observations'),
        ], $page->defineGates());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Unit economics viewer '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => ['finance-unit-economics.view']],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(CustomerUnitEconomicsReport::getUrl())
            ->assertSuccessful()
            ->assertSee('Operational unit-economics preview only.')
            ->assertDontSee('Record acquisition cost evidence')
            ->assertDontSee('Calculate CAC');
    }

    public function test_cost_entry_and_calculation_forms_require_their_own_permissions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'Unit economics operator '.uniqid(),
            'created_by_name' => 'Test',
            'access' => ['Finance' => [
                'finance-unit-economics.view',
                'finance-unit-economics.calculate',
                'finance-unit-economics.manage-costs',
                'finance-unit-economics.manage-retention',
            ]],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(CustomerUnitEconomicsReport::getUrl())
            ->assertSuccessful()
            ->assertSee('Record acquisition cost evidence')
            ->assertSee('Record explicit retention evidence')
            ->assertSee('Calculate CAC');
    }
}
