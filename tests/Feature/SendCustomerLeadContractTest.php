<?php

namespace Tests\Feature;

use App\Actions\SendCustomerLeadContract;
use App\Filament\Resources\CustomerLeads\Pages\ListCustomerLeads;
use App\Jobs\SendCustomerLeadContractEmail;
use App\Mail\CustomerLeadContractMail;
use App\Models\CustomerLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SendCustomerLeadContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('local');
    }

    public function test_preflight_requires_valid_recipient_and_existing_pdf(): void
    {
        $lead = CustomerLead::factory()->create(['email' => '', 'contract_document' => null]);

        try {
            app(SendCustomerLeadContract::class)->handle($lead);
            $this->fail('A missing recipient should block contract delivery.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $lead->update(['email' => 'valid@example.test']);

        try {
            app(SendCustomerLeadContract::class)->handle($lead);
            $this->fail('A missing contract PDF should block delivery.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contract_document', $exception->errors());
        }

        $this->assertSame('not_sent', $lead->fresh()->contract_send_status);
    }

    public function test_contract_is_queued_then_marked_sent_only_after_mail_transport_succeeds(): void
    {
        Queue::fake();
        Mail::fake();
        $lead = $this->leadWithContract();

        app(SendCustomerLeadContract::class)->handle($lead);

        $this->assertSame('queued', $lead->fresh()->contract_send_status);
        $this->assertNull($lead->fresh()->contract_sent_at);
        Queue::assertPushed(SendCustomerLeadContractEmail::class, fn ($job): bool => $job->customerLeadId === $lead->id);

        (new SendCustomerLeadContractEmail($lead->id))->handle();

        Mail::assertSent(CustomerLeadContractMail::class, fn (CustomerLeadContractMail $mail): bool => $mail->hasTo('lead@example.test'));
        $this->assertSame('sent', $lead->fresh()->contract_send_status);
        $this->assertNotNull($lead->fresh()->contract_sent_at);
        $this->assertNull($lead->fresh()->contract_send_error);
    }

    public function test_duplicate_send_is_rejected_while_queued_or_after_success(): void
    {
        Queue::fake();
        $lead = $this->leadWithContract();

        app(SendCustomerLeadContract::class)->handle($lead);

        try {
            app(SendCustomerLeadContract::class)->handle($lead);
            $this->fail('A second send should be rejected while the first is queued.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contract_send_status', $exception->errors());
        }

        $lead->update(['contract_send_status' => 'sent', 'contract_sent_at' => now()]);

        $this->expectException(ValidationException::class);
        app(SendCustomerLeadContract::class)->handle($lead);
    }

    public function test_job_failure_records_error_without_a_sent_timestamp(): void
    {
        $lead = $this->leadWithContract();
        $lead->update(['contract_send_status' => 'queued']);
        Storage::disk('local')->delete($lead->contract_document);
        $job = new SendCustomerLeadContractEmail($lead->id);

        try {
            $job->handle();
            $this->fail('The job should fail if its PDF disappears before delivery.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }

        $lead->refresh();
        $this->assertSame('failed', $lead->contract_send_status);
        $this->assertNull($lead->contract_sent_at);
        $this->assertStringContainsString('PDF is missing', $lead->contract_send_error);
    }

    public function test_send_action_is_permission_gated(): void
    {
        Queue::fake();
        $allowAll = false;
        Gate::before(function ($user, string $ability) use (&$allowAll): bool {
            return $allowAll || $ability === 'customer_lead.index';
        });
        $user = User::factory()->create();
        $lead = $this->leadWithContract();
        $this->actingAs($user);

        Livewire::test(ListCustomerLeads::class)
            ->assertTableActionHidden('sendContract', $lead);

        $allowAll = true;
        Livewire::test(ListCustomerLeads::class)
            ->assertTableActionVisible('sendContract', $lead)
            ->callTableAction('sendContract', $lead, data: ['confirm_send' => true])
            ->assertHasNoTableActionErrors();

        $this->assertSame('queued', $lead->fresh()->contract_send_status);
        Queue::assertPushed(SendCustomerLeadContractEmail::class);
    }

    private function leadWithContract(): CustomerLead
    {
        Storage::disk('local')->put('customer-lead-contracts/contract.pdf', '%PDF-1.4 test');

        return CustomerLead::factory()->create([
            'email' => 'lead@example.test',
            'contract_document' => 'customer-lead-contracts/contract.pdf',
            'contract_send_status' => 'not_sent',
        ]);
    }
}