<?php

namespace Tests\Feature;

use App\Filament\Resources\Sessions\SessionResource;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SessionResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_group_permissions_only_expose_and_create_group_sessions(): void
    {
        Gate::before(fn ($user, string $ability): bool => $ability === 'group_session.index' || $ability === 'group_session.create');
        $this->actingAs(User::factory()->create());

        $group = Session::factory()->create(['session_type' => Session::TYPE_GROUP]);
        Session::factory()->individual()->create();

        $this->assertSame([$group->id], SessionResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(SessionResource::canCreateType(Session::TYPE_GROUP));
        $this->assertFalse(SessionResource::canCreateType(Session::TYPE_INDIVIDUAL));
    }
}
