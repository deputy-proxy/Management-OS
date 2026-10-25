<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Finance\PaymentAllocationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAllocationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_proposing_allocation_requires_transaction_update_permission(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $actor = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(PaymentAllocationService::class)->propose(
            $actor,
            999999,
            'fee',
            '1.00',
            'RON',
            null,
            null,
            'Unauthorized allocation attempt',
        );
    }
}
