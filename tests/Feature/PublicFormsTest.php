<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_lead_submission_uses_server_controlled_fields(): void
    {
        $this->post(route('public.leads.store'), [
            'first_name' => 'Ari',
            'middle_name' => '',
            'last_name' => 'Pop',
            'email' => 'ari@example.test',
            'phone' => '0700000000',
            'topic_interest' => 'English',
            'product_interest' => 'Kids course',
            'privacy_consent' => '1',
            'status' => 'converted',
            'converted_customer_id' => 999,
        ])->assertRedirect();

        $this->assertDatabaseHas('customer_leads', [
            'email' => 'ari@example.test',
            'source' => 'public_form',
            'status' => 'new',
            'converted_customer_id' => null,
        ]);
    }

    public function test_lead_requires_consent_and_valid_fields(): void
    {
        $this->from(route('public.leads.create'))->post(route('public.leads.store'), [
            'first_name' => 'Ari',
            'last_name' => 'Pop',
            'email' => 'not-an-email',
            'phone' => '0700000000',
            'topic_interest' => 'English',
            'product_interest' => 'Kids course',
        ])->assertSessionHasErrors(['email', 'privacy_consent']);
        $this->assertDatabaseCount('customer_leads', 0);
    }

    public function test_newsletter_subscription_is_idempotent_by_email(): void
    {
        $payload = [
            'first_name' => 'Ari',
            'last_name' => 'Pop',
            'email' => 'ari@example.test',
            'privacy_consent' => '1',
        ];

        $this->post(route('public.newsletter.store'), $payload)->assertRedirect();
        $this->post(route('public.newsletter.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_competition_entry_is_bound_to_route_competition(): void
    {
        $competition = Competition::query()->create([
            'title' => 'Test competition',
            'description' => 'Test',
            'start_on' => now()->subDay()->toDateString(),
            'end_on' => now()->addDay()->toDateString(),
            'maximum_entries' => 10,
            'rules' => [],
        ]);

        $this->post(route('public.competitions.entries.store', $competition), [
            'first_name' => 'Ari',
            'last_name' => 'Pop',
            'email' => 'ari@example.test',
            'title' => 'Entry',
            'content' => 'My entry',
            'privacy_consent' => '1',
            'competition_id' => 999,
        ])->assertRedirect();

        $this->assertDatabaseHas('competition_entries', [
            'competition_id' => $competition->id,
            'email' => 'ari@example.test',
            'status' => 'pending_review',
        ]);
        $this->assertNotNull(CompetitionEntry::query()->where('email', 'ari@example.test')->value('privacy_consented_at'));
        $this->assertDatabaseMissing('competition_entries', ['competition_id' => 999]);
    }

    public function test_review_rejects_unlisted_product_types_and_non_customer_email(): void
    {
        $this->from(route('public.reviews.create'))->post(route('public.reviews.store'), [
            'product_type' => '\\App\\Models\\User',
            'product_id' => 1,
            'reviewer_name' => 'Ari',
            'reviewer_email' => 'unknown@example.test',
            'content' => 'Good',
            'rating' => 5,
            'privacy_consent' => '1',
        ])->assertSessionHasErrors(['product_type']);

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_honeypot_blocks_submission(): void
    {
        $this->post(route('public.leads.store'), [
            'first_name' => 'Ari',
            'last_name' => 'Pop',
            'email' => 'ari@example.test',
            'phone' => '0700000000',
            'topic_interest' => 'English',
            'product_interest' => 'Kids course',
            'privacy_consent' => '1',
            'website' => 'spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('customer_leads', 0);
    }

    public function test_public_submission_routes_are_rate_limited(): void
    {
        $payload = [
            'first_name' => 'Ari',
            'last_name' => 'Pop',
            'email' => 'ari@example.test',
            'phone' => '0700000000',
            'topic_interest' => 'English',
            'product_interest' => 'Kids course',
            'privacy_consent' => '1',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('public.leads.store'), $payload)->assertRedirect();
        }

        $this->post(route('public.leads.store'), $payload)->assertTooManyRequests();
    }
}
