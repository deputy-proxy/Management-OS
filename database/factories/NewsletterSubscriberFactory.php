<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NewsletterSubscriberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<NewsletterSubscriber>
     */
    protected $model = NewsletterSubscriber::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->sentence(),
            'last_name' => $this->faker->sentence(),
            'email' => $this->faker->unique()->safeEmail(),
            'customer_id' => \App\Models\Customer::factory(),
        ];
    }
}