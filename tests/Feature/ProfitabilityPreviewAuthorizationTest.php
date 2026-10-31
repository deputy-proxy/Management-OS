<?php

namespace Tests\Feature;

use App\Filament\Pages\ProfitabilityPreview;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Provisional operational calculation only.')
            ->assertDontSee('Create provisional run');
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
