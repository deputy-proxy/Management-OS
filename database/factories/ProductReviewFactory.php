<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Customer;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ProductReview>
     */
    protected $model = ProductReview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_type' => 'course',
            'product_id' => Course::factory(),
            'reviewer_name' => $this->faker->sentence(),
            'reviewer_email' => $this->faker->unique()->safeEmail(),
            'customer_id' => Customer::factory(),
            'content' => $this->faker->paragraphs(3, true),
            'rating' => $this->faker->numberBetween(1, 5),
            'reviewed_on' => $this->faker->dateTime(),
        ];
    }
}
