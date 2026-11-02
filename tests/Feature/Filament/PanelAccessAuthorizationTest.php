<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Camps\CampResource;
use App\Filament\Resources\ProductReviews\ProductReviewResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_user_without_an_assigned_role_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_user_with_a_role_that_has_no_permissions_cannot_access_the_panel(): void
    {
        $user = User::factory()->create();
        $role = $this->createRole([]);
        $user->roles()->attach($role->getKey());

        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_user_with_an_explicitly_assigned_permission_can_access_the_panel(): void
    {
        $user = User::factory()->create();
        $role = $this->createRole(['Students' => ['student.index']]);
        $user->roles()->attach($role->getKey());

        $this->assertTrue($user->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_authenticated_user_without_a_role_is_denied_from_admin_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_user_with_an_explicit_role_can_load_the_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $role = $this->createRole(['Students' => ['student.index']]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_owner_can_access_clustered_resources_without_individual_permissions(): void
    {
        $user = User::factory()->create();
        $role = $this->createRole([]);
        $role->update(['name' => 'Owner']);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(CampResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_role_permission_keys_are_checked_when_the_user_is_not_an_owner(): void
    {
        $user = User::factory()->create();
        $role = $this->createRole(['Camp Management' => ['camp.index']]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(CampResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_panel_admission_does_not_grant_unrelated_resource_access(): void
    {
        $user = User::factory()->create();
        $role = $this->createRole(['Students' => ['student.index']]);
        $user->roles()->attach($role->getKey());

        $this->actingAs($user)
            ->get(ProductReviewResource::getUrl('index'))
            ->assertForbidden();
    }

    /** @param array<string, array<int, string>> $access */
    private function createRole(array $access): object
    {
        $roleClass = config('hexa.models.role');

        return $roleClass::create([
            'name' => 'Test role '.uniqid(),
            'created_by_name' => 'Test',
            'access' => $access,
            'guard' => 'web',
        ]);
    }
}
