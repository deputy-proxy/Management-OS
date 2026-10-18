<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Course;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\NewsletterSubscriber;
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

    public function test_competition_capacity_rejects_entry_at_exact_limit(): void
    {
        $competition = Competition::query()->create([
            'title' => 'Capacity competition',
            'description' => 'Test',
            'start_on' => now()->subDay()->toDateString(),
            'end_on' => now()->addDay()->toDateString(),
            'maximum_entries' => 1,
            'rules' => [],
        ]);

        $payload = [
            'first_name' => 'Ari', 'last_name' => 'Pop', 'email' => 'first@example.test',
            'title' => 'Entry', 'content' => 'My entry', 'privacy_consent' => '1',
        ];
        $this->post(route('public.competitions.entries.store', $competition), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->from(route('public.competitions.entries.create', $competition))
            ->post(route('public.competitions.entries.store', $competition), array_merge($payload, ['email' => 'second@example.test']))
            ->assertSessionHasErrors('competition');

        $this->assertDatabaseCount('competition_entries', 1);
    }

    public function test_competition_with_zero_maximum_entries_is_unlimited(): void
    {
        $competition = Competition::query()->create([
            'title' => 'Unlimited competition', 'description' => 'Test',
            'start_on' => now()->subDay()->toDateString(), 'end_on' => now()->addDay()->toDateString(),
            'maximum_entries' => 0, 'rules' => [],
        ]);

        foreach (['one@example.test', 'two@example.test'] as $email) {
            $this->post(route('public.competitions.entries.store', $competition), [
                'first_name' => 'Ari', 'last_name' => 'Pop', 'email' => $email,
                'title' => 'Entry', 'content' => 'My entry', 'privacy_consent' => '1',
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('competition_entries', 2);
    }

    public function test_future_and_closed_competitions_reject_submissions(): void
    {
        foreach ([
            ['start_on' => now()->addDay()->toDateString(), 'end_on' => now()->addDays(2)->toDateString()],
            ['start_on' => now()->subDays(2)->toDateString(), 'end_on' => now()->subDay()->toDateString()],
        ] as $dates) {
            $competition = Competition::query()->create(array_merge([
                'title' => 'Unavailable competition', 'description' => 'Test',
                'maximum_entries' => 10, 'rules' => [],
            ], $dates));

            $this->post(route('public.competitions.entries.store', $competition), [
                'first_name' => 'Ari', 'last_name' => 'Pop', 'email' => uniqid().'@example.test',
                'title' => 'Entry', 'content' => 'My entry', 'privacy_consent' => '1',
            ])->assertNotFound();
        }

        $this->assertDatabaseCount('competition_entries', 0);
    }

    public function test_competition_entry_database_constraint_rejects_duplicate_email_even_when_bypassing_service(): void
    {
        $competition = Competition::query()->create([
            'title' => 'Unique competition', 'description' => 'Test',
            'start_on' => now()->subDay()->toDateString(), 'end_on' => now()->addDay()->toDateString(),
            'maximum_entries' => 10, 'rules' => [],
        ]);
        $attributes = [
            'competition_id' => $competition->id, 'first_name' => 'Ari', 'last_name' => 'Pop',
            'full_name' => 'Ari Pop', 'email' => 'duplicate@example.test', 'title' => 'Entry',
            'content' => 'My entry', 'privacy_consented_at' => now(), 'status' => 'pending_review',
        ];
        CompetitionEntry::query()->create($attributes);

        $this->expectException(QueryException::class);
        CompetitionEntry::query()->create($attributes);
    }

    public function test_racing_competition_entry_returns_controlled_duplicate_error(): void
    {
        $competition = Competition::query()->create([
            'title' => 'Racing competition', 'description' => 'Test',
            'start_on' => now()->subDay()->toDateString(), 'end_on' => now()->addDay()->toDateString(),
            'maximum_entries' => 10, 'rules' => [],
        ]);
        $insertedConcurrentEntry = false;
        CompetitionEntry::creating(function (CompetitionEntry $entry) use (&$insertedConcurrentEntry): void {
            if ($insertedConcurrentEntry) {
                return;
            }
            $insertedConcurrentEntry = true;
            DB::table('competition_entries')->insert([
                'competition_id' => $entry->competition_id, 'first_name' => 'Concurrent', 'last_name' => 'Entry',
                'full_name' => 'Concurrent Entry', 'email' => $entry->email, 'title' => 'Concurrent',
                'content' => json_encode('Concurrent entry', JSON_THROW_ON_ERROR), 'privacy_consented_at' => now(),
                'status' => 'pending_review', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->from(route('public.competitions.entries.create', $competition))
            ->post(route('public.competitions.entries.store', $competition), [
                'first_name' => 'Ari', 'last_name' => 'Pop', 'email' => 'racing@example.test',
                'title' => 'Entry', 'content' => 'My entry', 'privacy_consent' => '1',
            ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('competition_entries', 0);
    }

    public function test_racing_lead_submission_returns_a_controlled_duplicate_error(): void
    {
        $insertedConcurrentLead = false;
        CustomerLead::creating(function (CustomerLead $lead) use (&$insertedConcurrentLead): void {
            if ($insertedConcurrentLead) {
                return;
            }
            $insertedConcurrentLead = true;
            DB::table('customer_leads')->insert([
                'first_name' => 'Concurrent', 'middle_name' => '', 'last_name' => 'Lead',
                'full_name' => 'Concurrent Lead', 'email' => $lead->email, 'phone' => '0700000000',
                'source' => 'public_form', 'topic_interest' => 'English', 'product_interest' => 'Course',
                'status' => 'new', 'last_contacted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->from(route('public.leads.create'))->post(route('public.leads.store'), [
            'first_name' => 'Ari', 'last_name' => 'Pop', 'email' => 'racing-lead@example.test',
            'phone' => '0700000000', 'topic_interest' => 'English', 'product_interest' => 'Course',
            'privacy_consent' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('customer_leads', 1);
    }

    public function test_racing_newsletter_subscription_remains_idempotent(): void
    {
        $insertedConcurrentSubscriber = false;
        NewsletterSubscriber::creating(function (NewsletterSubscriber $subscriber) use (&$insertedConcurrentSubscriber): void {
            if ($insertedConcurrentSubscriber) {
                return;
            }
            $insertedConcurrentSubscriber = true;
            DB::table('newsletter_subscribers')->insert([
                'first_name' => 'Concurrent', 'last_name' => 'Subscriber', 'email' => $subscriber->email,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->post(route('public.newsletter.store'), [
            'first_name' => 'Ari', 'last_name' => 'Pop', 'email' => 'racing-newsletter@example.test',
            'privacy_consent' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }
}
