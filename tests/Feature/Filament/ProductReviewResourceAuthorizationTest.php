<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProductReviews\ProductReviewResource;
use App\Models\Course;
use App\Models\Customer;
use App\Models\ProductReview;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProductReviewResourceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Gate::before(fn (): bool => false);
    }

    public function test_edit_page_denies_users_without_product_review_update_permission(): void
    {
        $user = User::factory()->create(['email' => 'review-denied@example.test']);
        $roleClass = config('hexa.models.role');
        $role = $roleClass::create([
            'name' => 'No product review access',
            'created_by_name' => 'Test',
            'access' => [],
            'guard' => 'web',
        ]);
        $user->roles()->attach($role->getKey());
        $customer = Customer::factory()->create();
        $course = Course::factory()->create();
        $review = ProductReview::factory()->create([
            'product_type' => 'course',
            'product_id' => $course->getKey(),
            'customer_id' => $customer->getKey(),
        ]);

        $this->actingAs($user)
            ->get(ProductReviewResource::getUrl('edit', ['record' => $review]))
            ->assertForbidden();
    }
}
