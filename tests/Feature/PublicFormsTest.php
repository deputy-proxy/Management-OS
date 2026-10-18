<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Course;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\ProductReview;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_public_lead_submission_rejects_duplicate_active_email(): void
    {
        CustomerLead::factory()->create(['email' => 'duplicate@example.test']);

        $this->from('/public/enquiries')->post('/public/enquiries', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'duplicate@example.test',
            'phone' => '+40123456789',
            'topic_interest' => 'English',
            'product_interest' => 'Course',
            'privacy_consent' => '1',
        ])->assertRedirect('/public/enquiries')->assertSessionHasErrors('email');

        $this->assertDatabaseCount('customer_leads', 1);
    }

    public function test_competition_entry_links_existing_customer_and_rejects_duplicate_email_per_competition(): void
    {
        $customer = Customer::factory()->create(['email' => 'ari@example.test']);
        $competition = Competition::query()->create([
            'title' => 'Customer competition',
            'description' => 'Test',
            'start_on' => now()->subDay()->toDateString(),
            'end_on' => now()->addDay()->toDateString(),
            'maximum_entries' => 10,
            'rules' => [],
        ]);
        $payload = [
            'first_name' => 'Ari',
            'last_name' => 'Pop',
            'email' => 'ari@example.test',
            'title' => 'Entry',
            'content' => 'My entry',
            'privacy_consent' => '1',
        ];

        $this->post(route('public.competitions.entries.store', $competition), $payload)->assertRedirect();

        $entry = CompetitionEntry::query()->sole();
        $this->assertSame($competition->id, $entry->competition_id);
        $this->assertSame($customer->id, $entry->customer_id);
        $this->assertTrue($customer->fresh()->competitionEntries()->sole()->is($entry));

        $this->from(route('public.competitions.entries.create', $competition))
            ->post(route('public.competitions.entries.store', $competition), $payload)
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('competition_entries', 1);
    }

    public function test_public_review_links_product_and_customer_and_starts_pending_moderation(): void
    {
        $customer = Customer::factory()->create(['email' => 'reviewer@example.test']);
        $course = Course::factory()->create();
        $payload = [
            'product_type' => 'course',
            'product_id' => $course->id,
            'reviewer_name' => 'Ari Pop',
            'reviewer_email' => 'reviewer@example.test',
            'content' => 'A useful course.',
            'rating' => 5,
            'privacy_consent' => '1',
            'status' => 'approved',
        ];

        $this->post(route('public.reviews.store'), $payload)->assertRedirect();

        $review = ProductReview::query()->sole();
        $this->assertSame('pending_review', $review->status);
        $this->assertSame($customer->id, $review->customer_id);
        $this->assertSame($course->id, $review->reviewedProduct->id);
        $this->assertTrue($course->reviews()->sole()->is($review));
        $this->assertTrue($customer->productReviews()->sole()->is($review));
        $this->assertNotNull($review->privacy_consented_at);
    }

    public function test_public_review_rejects_invalid_rating_unknown_product_and_non_customer(): void
    {
        $course = Course::factory()->create();
        $base = [
            'product_type' => 'course',
            'product_id' => $course->id,
            'reviewer_name' => 'Ari Pop',
            'reviewer_email' => 'unknown@example.test',
            'content' => 'Good',
            'rating' => 5,
            'privacy_consent' => '1',
        ];

        $this->from(route('public.reviews.create'))->post(route('public.reviews.store'), array_merge($base, ['product_type' => 'user']))
            ->assertSessionHasErrors('product_type');
        $this->from(route('public.reviews.create'))->post(route('public.reviews.store'), array_merge($base, ['rating' => 6]))
            ->assertSessionHasErrors('rating');
        $this->from(route('public.reviews.create'))->post(route('public.reviews.store'), array_merge($base, ['product_id' => 999999]))
            ->assertNotFound();
        $this->from(route('public.reviews.create'))->post(route('public.reviews.store'), $base)
            ->assertSessionHasErrors('reviewer_email');

        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_customer_cannot_submit_more_than_one_review_for_the_same_product(): void
    {
        $customer = Customer::factory()->create(['email' => 'reviewer@example.test']);
        $course = Course::factory()->create();
        $payload = [
            'product_type' => 'course',
            'product_id' => $course->id,
            'reviewer_name' => 'Ari Pop',
            'reviewer_email' => 'reviewer@example.test',
            'content' => 'A useful course.',
            'rating' => 4,
            'privacy_consent' => '1',
        ];

        $this->post(route('public.reviews.store'), $payload)->assertRedirect();
        $this->from(route('public.reviews.create'))->post(route('public.reviews.store'), $payload)
            ->assertSessionHasErrors('reviewer_email');

        $this->assertDatabaseCount('product_reviews', 1);
        $this->assertDatabaseHas('product_reviews', ['customer_id' => $customer->id, 'status' => 'pending_review']);
    }

    public function test_customer_can_replace_a_soft_deleted_review_for_the_same_product(): void
    {
        $customer = Customer::factory()->create(['email' => 'reviewer@example.test']);
        $course = Course::factory()->create();
        $deletedReview = ProductReview::factory()->create([
            'product_type' => 'course',
            'product_id' => $course->id,
            'customer_id' => $customer->id,
            'reviewer_email' => $customer->email,
        ]);
        $deletedReview->delete();

        $this->from(route('public.reviews.create'))->post(route('public.reviews.store'), [
            'product_type' => 'course',
            'product_id' => $course->id,
            'reviewer_name' => 'Ari Pop',
            'reviewer_email' => $customer->email,
            'content' => 'A replacement review.',
            'rating' => 5,
            'privacy_consent' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('product_reviews', 2);
        $this->assertSame(1, ProductReview::query()->where('customer_id', $customer->id)->where('product_id', $course->id)->count());
        $this->assertNotNull($deletedReview->fresh()->deleted_at);
        $this->assertDatabaseHas('product_reviews', [
            'customer_id' => $customer->id,
            'product_id' => $course->id,
            'status' => 'pending_review',
            'deleted_at' => null,
        ]);
    }

    public function test_database_constraint_rejects_a_second_active_review_bypassing_the_controller_check(): void
    {
        $customer = Customer::factory()->create();
        $course = Course::factory()->create();
        ProductReview::factory()->create([
            'product_type' => 'course',
            'product_id' => $course->id,
            'customer_id' => $customer->id,
        ]);

        $this->expectException(QueryException::class);

        ProductReview::factory()->create([
            'product_type' => 'course',
            'product_id' => $course->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_racing_review_submission_returns_a_controlled_duplicate_error(): void
    {
        $customer = Customer::factory()->create(['email' => 'reviewer@example.test']);
        $course = Course::factory()->create();
        $insertedConcurrentReview = false;

        ProductReview::creating(function () use ($customer, $course, &$insertedConcurrentReview): void {
            if ($insertedConcurrentReview) {
                return;
            }

            $insertedConcurrentReview = true;
            DB::table('product_reviews')->insert([
                'product_type' => 'course',
                'product_id' => $course->id,
                'reviewer_name' => 'Concurrent reviewer',
                'reviewer_email' => $customer->email,
                'customer_id' => $customer->id,
                'content' => 'Concurrent review inserted after the pre-check.',
                'rating' => 5,
                'reviewed_on' => now(),
                'privacy_consented_at' => now(),
                'status' => 'pending_review',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->from(route('public.reviews.create'))->post(route('public.reviews.store'), [
            'product_type' => 'course',
            'product_id' => $course->id,
            'reviewer_name' => 'Ari Pop',
            'reviewer_email' => $customer->email,
            'content' => 'A racing review.',
            'rating' => 4,
            'privacy_consent' => '1',
        ])->assertRedirect()->assertSessionHasErrors('reviewer_email');

        $this->assertDatabaseCount('product_reviews', 1);
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