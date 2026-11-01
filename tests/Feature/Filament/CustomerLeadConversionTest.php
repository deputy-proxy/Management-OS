<?php

namespace Tests\Feature\Filament;

use App\Actions\ConvertCustomerLead;
use App\Events\CustomerLeadConverted;
use App\Filament\Resources\CustomerLeads\Pages\ListCustomerLeads;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerLead;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CustomerLeadConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_signed_lead_conversion_creates_customer_and_contract_and_marks_the_lead(): void
    {
        $lead = CustomerLead::factory()->create([
            'first_name' => 'Ada',
            'middle_name' => 'M.',
            'last_name' => 'Lovelace',
            'full_name' => 'Ada M. Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+40123456789',
            'status' => 'qualified',
        ]);

        $customer = app(ConvertCustomerLead::class)->handle($lead, $this->conversionData());

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->getKey(),
            'name' => 'Ada M. Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+40123456789',
            'customer_type' => 'Individual',
            'cnp_cui' => '1234567890123',
            'address' => '1 Example Street',
        ]);
        $this->assertDatabaseHas('customer_contracts', [
            'customer_id' => $customer->getKey(),
            'source_customer_lead_id' => $lead->getKey(),
            'contract_code' => 'ADA-2026-001',
            'document' => 'customer-contracts/signed.pdf',
        ]);

        $contract = CustomerContract::query()->where('source_customer_lead_id', $lead->getKey())->firstOrFail();
        $this->assertNotNull($contract->signed_at);

        $lead->refresh();
        $this->assertSame('converted', $lead->status);
        $this->assertSame($customer->getKey(), $lead->converted_customer_id);
        $this->assertNotNull($lead->converted_at);
        $this->assertSame(1, $lead->attributionEvents()->where('event_type', 'conversion')->count());
        $this->assertSame($customer->getKey(), $lead->attributionEvents()->where('event_type', 'conversion')->first()->snapshot['conversion']['customer_id']);
        $this->assertSame($customer->getKey(), $lead->convertedCustomer->getKey());
        $this->assertSame($contract->getKey(), $lead->customerContract->getKey());
    }

    public function test_conversion_reuses_an_existing_customer_with_matching_identity(): void
    {
        $lead = CustomerLead::factory()->create([
            'full_name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+40123456789',
        ]);
        $customer = Customer::factory()->create([
            'name' => 'Ada Existing Customer',
            'customer_type' => 'Individual',
            'cnp_cui' => '1234567890123',
            'email' => 'ada@example.test',
            'phone' => null,
        ]);

        $convertedCustomer = app(ConvertCustomerLead::class)->handle($lead, $this->conversionData());

        $this->assertSame($customer->getKey(), $convertedCustomer->getKey());
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->getKey(),
            'name' => 'Ada Existing Customer',
            'phone' => '+40123456789',
        ]);
        $this->assertDatabaseHas('customer_contracts', [
            'customer_id' => $customer->getKey(),
            'source_customer_lead_id' => $lead->getKey(),
        ]);
    }

    public function test_conversion_rejects_conflicting_existing_customer_identity(): void
    {
        $lead = CustomerLead::factory()->create([
            'email' => 'shared@example.test',
        ]);
        Customer::factory()->create([
            'cnp_cui' => 'DIFFERENT-ID',
            'email' => 'shared@example.test',
        ]);

        try {
            app(ConvertCustomerLead::class)->handle($lead, $this->conversionData());
            $this->fail('Expected conflicting customer identity to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cnp_cui', $exception->errors());
        }

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_contracts', 0);
        $this->assertDatabaseHas('customer_leads', [
            'id' => $lead->getKey(),
            'converted_customer_id' => null,
            'converted_at' => null,
        ]);
    }

    public function test_conversion_is_rejected_without_confirmation_of_a_signed_contract(): void
    {
        $lead = CustomerLead::factory()->create();

        $this->expectException(ValidationException::class);

        try {
            app(ConvertCustomerLead::class)->handle(
                $lead,
                array_merge($this->conversionData(), ['confirm_signed' => false]),
            );
        } finally {
            $this->assertDatabaseCount('customers', 0);
            $this->assertDatabaseCount('customer_contracts', 0);
            $this->assertDatabaseHas('customer_leads', [
                'id' => $lead->getKey(),
                'converted_customer_id' => null,
                'converted_at' => null,
            ]);
        }
    }

    public function test_a_lead_cannot_be_converted_twice(): void
    {
        $lead = CustomerLead::factory()->create();
        $service = app(ConvertCustomerLead::class);

        $service->handle($lead, $this->conversionData());

        try {
            $service->handle($lead->refresh(), $this->conversionData());
            $this->fail('Expected repeated conversion to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('record', $exception->errors());
        }

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('customer_contracts', 1);
    }

    public function test_conversion_event_is_an_explicit_after_commit_business_event(): void
    {
        $this->assertContains(
            ShouldDispatchAfterCommit::class,
            class_implements(CustomerLeadConverted::class),
        );

        $lead = CustomerLead::factory()->create();
        Event::fake([CustomerLeadConverted::class]);

        try {
            DB::transaction(function () use ($lead): void {
                app(ConvertCustomerLead::class)->handle($lead, $this->conversionData());
                Event::assertNotDispatched(CustomerLeadConverted::class);
                throw new RuntimeException('Rollback conversion transaction.');
            });
            $this->fail('Expected the transaction to roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback conversion transaction.', $exception->getMessage());
        }

        Event::assertNotDispatched(CustomerLeadConverted::class);
        $this->assertDatabaseCount('customer_contracts', 0);
        $this->assertDatabaseHas('customer_leads', ['id' => $lead->getKey(), 'converted_customer_id' => null]);
    }

    public function test_customer_and_contract_creation_roll_back_together_on_failure(): void
    {
        $lead = CustomerLead::factory()->create();

        Event::listen('eloquent.creating: '.CustomerContract::class, static function (): void {
            throw new RuntimeException('Simulated contract persistence failure.');
        });

        try {
            app(ConvertCustomerLead::class)->handle($lead, $this->conversionData());
            $this->fail('Expected the simulated contract failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated contract persistence failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_contracts', 0);
        $this->assertDatabaseHas('customer_leads', [
            'id' => $lead->getKey(),
            'converted_customer_id' => null,
            'converted_at' => null,
        ]);
    }

    public function test_authorized_user_can_convert_a_lead_through_the_table_action(): void
    {
        Gate::before(fn (): bool => true);
        Storage::fake('public');

        $user = User::factory()->create();
        $lead = CustomerLead::factory()->create([
            'full_name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'phone' => '+40123456789',
            'status' => 'qualified',
        ]);

        $this->actingAs($user);

        Livewire::test(ListCustomerLeads::class)
            ->callTableAction('turnIntoCustomer', $lead, data: [
                'customer_type' => 'Individual',
                'cnp_cui' => '9876543210123',
                'address' => '2 Example Avenue',
                'contract_code' => 'GRACE-2026-001',
                'start_date' => '2026-10-01',
                'end_date' => '2027-10-01',
                'document' => UploadedFile::fake()->create('signed-contract.pdf', 100, 'application/pdf'),
                'confirm_signed' => true,
            ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.test',
            'phone' => '+40123456789',
        ]);
        $this->assertDatabaseHas('customer_contracts', [
            'contract_code' => 'GRACE-2026-001',
            'source_customer_lead_id' => $lead->getKey(),
        ]);
        $this->assertDatabaseHas('customer_leads', [
            'id' => $lead->getKey(),
            'status' => 'converted',
        ]);
    }

    public function test_conversion_form_rejects_missing_customer_and_contract_fields(): void
    {
        Gate::before(fn (): bool => true);

        $user = User::factory()->create();
        $lead = CustomerLead::factory()->create();

        $this->actingAs($user);

        Livewire::test(ListCustomerLeads::class)
            ->callTableAction('turnIntoCustomer', $lead, data: [
                'customer_type' => 'Individual',
            ])
            ->assertHasFormErrors([
                'cnp_cui' => 'required',
                'address' => 'required',
                'contract_code' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
                'document' => 'required',
                'confirm_signed' => 'accepted',
            ]);

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_contracts', 0);
    }

    public function test_conversion_action_is_hidden_when_user_lacks_conversion_permissions(): void
    {
        Gate::before(fn ($user, string $ability): bool => $ability === 'customer_lead.index');

        $user = User::factory()->create();
        $lead = CustomerLead::factory()->create();

        $this->actingAs($user);

        Livewire::test(ListCustomerLeads::class)
            ->assertTableActionHidden('turnIntoCustomer', $lead);
    }

    public function test_conversion_action_is_hidden_for_a_lead_that_is_already_converted(): void
    {
        Gate::before(fn (): bool => true);

        $user = User::factory()->create();
        $lead = CustomerLead::factory()->create([
            'status' => 'converted',
            'converted_customer_id' => Customer::factory(),
            'converted_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test(ListCustomerLeads::class)
            ->assertTableActionHidden('turnIntoCustomer', $lead);
    }

    /**
     * @return array<string, mixed>
     */
    private function conversionData(): array
    {
        return [
            'customer_type' => 'Individual',
            'cnp_cui' => '1234567890123',
            'address' => '1 Example Street',
            'contract_code' => 'ADA-2026-001',
            'start_date' => '2026-10-01',
            'end_date' => '2027-10-01',
            'document' => 'customer-contracts/signed.pdf',
            'confirm_signed' => true,
        ];
    }
}
