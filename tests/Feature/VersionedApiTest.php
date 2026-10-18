<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_versioned_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_versioned_profile_requires_the_profile_read_ability(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('integration', ['employees:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/user')->assertForbidden();
    }

    public function test_versioned_profile_returns_only_the_authenticated_users_public_profile_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('integration', ['profile:read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('remember_token');
    }

    public function test_legacy_profile_route_remains_authenticated_and_returns_minimal_fields(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/user')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    public function test_versioned_profile_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('integration', ['profile:read'])->plainTextToken;

        for ($request = 0; $request < 60; $request++) {
            $this->withToken($token)->getJson('/api/v1/user')->assertOk();
        }

        $this->withToken($token)->getJson('/api/v1/user')->assertTooManyRequests();
    }
}
