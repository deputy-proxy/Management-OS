<?php

namespace App\Http\Controllers;

use App\Models\Camp;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Course;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\Event;
use App\Models\NewsletterSubscriber;
use App\Models\ProductReview;
use App\Models\Workshop;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicSubmissionController extends Controller
{
    public function leadForm(): View
    {
        return view('public-forms.lead');
    }

    public function submitLead(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('customer_leads', 'email')->whereNull('deleted_at')],
            'phone' => ['required', 'string', 'max:50'],
            'topic_interest' => ['required', 'string', 'max:255'],
            'product_interest' => ['required', 'string', 'max:255'],
            'privacy_consent' => ['accepted'],
            'website' => ['prohibited'],
        ]);

        CustomerLead::query()->create([
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? '',
            'last_name' => $data['last_name'],
            'full_name' => trim(implode(' ', array_filter([$data['first_name'], $data['middle_name'] ?? null, $data['last_name']]))),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'source' => 'public_form',
            'topic_interest' => $data['topic_interest'],
            'product_interest' => $data['product_interest'],
            'status' => 'new',
            'last_contacted_at' => now(),
            'privacy_consented_at' => now(),
        ]);

        return back()->with('status', __('Thank you. Your enquiry has been received.'));
    }

    public function competitionForm(Competition $competition): View|RedirectResponse
    {
        if ($competition->trashed() || ($competition->start_on && $competition->start_on > now()->toDateString()) || ($competition->end_on && $competition->end_on < now()->toDateString())) {
            abort(404);
        }

        return view('public-forms.competition-entry', compact('competition'));
    }

    public function submitCompetitionEntry(Request $request, Competition $competition): RedirectResponse
    {
        abort_if($competition->trashed() || ($competition->start_on && $competition->start_on > now()->toDateString()) || ($competition->end_on && $competition->end_on < now()->toDateString()), 404);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('competition_entries', 'email')->where('competition_id', $competition->getKey())],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
            'privacy_consent' => ['accepted'],
            'website' => ['prohibited'],
        ]);

        if ($competition->maximum_entries && $competition->entries()->count() >= $competition->maximum_entries) {
            return back()->withErrors(['competition' => __('This competition is no longer accepting entries.')])->withInput();
        }

        CompetitionEntry::query()->create([
            'competition_id' => $competition->getKey(),
            'customer_id' => Customer::query()->where('email', $data['email'])->value('id'),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'full_name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'title' => $data['title'],
            'content' => $data['content'],
            'privacy_consented_at' => now(),
            'status' => 'pending_review',
        ]);

        return back()->with('status', __('Your entry has been submitted for review.'));
    }

    public function newsletterForm(): View
    {
        return view('public-forms.newsletter');
    }

    public function subscribeNewsletter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'privacy_consent' => ['accepted'],
            'website' => ['prohibited'],
        ]);

        $existing = NewsletterSubscriber::query()->where('email', $data['email'])->first();
        if (! $existing) {
            NewsletterSubscriber::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'privacy_consented_at' => now(),
            ]);
        }

        return back()->with('status', __('Thank you. Your subscription request has been received.'));
    }

    public function reviewForm(): View
    {
        return view('public-forms.product-review');
    }

    public function submitReview(Request $request): RedirectResponse
    {
        $types = [
            'course' => Course::class,
            'camp' => Camp::class,
            'workshop' => Workshop::class,
            'event' => Event::class,
        ];

        $data = $request->validate([
            'product_type' => ['required', Rule::in(array_keys($types))],
            'product_id' => ['required', 'integer', 'min:1'],
            'reviewer_name' => ['required', 'string', 'max:255'],
            'reviewer_email' => ['required', 'email:rfc', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'privacy_consent' => ['accepted'],
            'website' => ['prohibited'],
        ]);

        $model = $types[$data['product_type']];
        abort_unless($model::query()->whereKey($data['product_id'])->exists(), 404);

        $customer = Customer::query()->where('email', $data['reviewer_email'])->first();
        if (! $customer) {
            return back()->withErrors(['reviewer_email' => __('Reviews are currently limited to existing customers. Please contact us if your customer email has changed.')])->withInput();
        }

        $duplicateReviewExists = ProductReview::query()
            ->where('product_type', $data['product_type'])
            ->where('product_id', $data['product_id'])
            ->where('customer_id', $customer->getKey())
            ->exists();

        if ($duplicateReviewExists) {
            return back()->withErrors(['reviewer_email' => __('You have already submitted a review for this product.')])->withInput();
        }

        try {
            ProductReview::query()->create([
                'product_type' => $data['product_type'],
                'product_id' => $data['product_id'],
                'reviewer_name' => $data['reviewer_name'],
                'reviewer_email' => $data['reviewer_email'],
                'customer_id' => $customer->getKey(),
                'content' => $data['content'],
                'rating' => $data['rating'],
                'reviewed_on' => now(),
                'privacy_consented_at' => now(),
                'status' => 'pending_review',
            ]);
        } catch (QueryException $exception) {
            if (! $this->isActiveReviewUniqueViolation($exception)) {
                throw $exception;
            }

            return back()->withErrors(['reviewer_email' => __('You have already submitted a review for this product.')])->withInput();
        }

        return back()->with('status', __('Thank you. Your review has been submitted for moderation.'));
    }

    private function isActiveReviewUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return str_contains($message, 'product_reviews_product_customer_unique')
            || ($sqlState === '23000' && $driverCode === 1062 && str_contains($message, 'product_reviews'))
            || ($sqlState === '23505' && str_contains($message, 'product_reviews'))
            || ($sqlState === '23000' && str_contains($message, 'unique constraint failed: product_reviews.'));
    }
}