<?php

namespace Tests\Feature\Framework;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class Laravel13CompatibilityTest extends TestCase
{
    public function test_existing_session_serialization_is_preserved_for_upgrade_compatibility(): void
    {
        $this->assertSame('php', config('session.serialization'));
    }

    public function test_cache_unserialization_is_restricted_to_prevent_arbitrary_object_deserialization(): void
    {
        $this->assertFalse(config('cache.serializable_classes'));
    }

    public function test_sanctum_uses_the_laravel_13_request_forgery_middleware(): void
    {
        $this->assertSame(
            PreventRequestForgery::class,
            config('sanctum.middleware.validate_csrf_token'),
        );
    }
}
