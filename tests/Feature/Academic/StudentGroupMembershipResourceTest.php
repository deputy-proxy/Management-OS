<?php

namespace Tests\Feature\Academic;

use App\Filament\Resources\StudentGroupMemberships\Pages\CreateStudentGroupMembership;
use App\Filament\Resources\StudentGroupMemberships\StudentGroupMembershipResource;
use App\Models\Group;
use App\Models\Student;
use App\Models\StudentGroupMembership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class StudentGroupMembershipResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_staff_with_permissions_can_create_a_membership_in_the_academic_panel(): void
    {
        Gate::before(fn (): bool => true);
        $student = Student::factory()->create();
        $group = Group::factory()->create();
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateStudentGroupMembership::class)
            ->fillForm([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'started_on' => '2026-01-10',
                'ended_on' => null,
            ], 'form')
            ->call('create', shouldRedirect: false)
            ->assertHasNoFormErrors([], 'form');

        $this->assertDatabaseHas('student_group_memberships', [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'ended_on' => null,
        ]);
        $this->assertSame('2026-01-10', StudentGroupMembership::query()->firstOrFail()->started_on->toDateString());
    }

    public function test_membership_resource_denies_users_without_view_permission(): void
    {
        Gate::before(fn (): bool => false);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(StudentGroupMembershipResource::getUrl('index'))
            ->assertForbidden();
    }
}