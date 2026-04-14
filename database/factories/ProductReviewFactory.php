<?php

namespace Database\Factories;

use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'product_type' => $this->faker->word(),
            'product_id' => \App\Models\Product::factory(),
            'reviewer_name' => $this->faker->sentence(),
            'reviewer_email' => $this->faker->unique()->safeEmail(),
            'customer_id' => \App\Models\Customer::factory(),
            'content' => $this->faker->paragraphs(3, true),
            'rating' => $this->faker->randomNumber(),
            'reviewed_on' => $this->faker->dateTime(),
        ];
    }
}