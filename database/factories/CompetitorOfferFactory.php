<?php

namespace Database\Factories;

use App\Models\Competitor;
use App\Models\CompetitorOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompetitorOfferFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CompetitorOffer>
     */
    protected $model = CompetitorOffer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'competitor_id' => Competitor::factory(),
            'offer_type' => $this->faker->word(),
            'offer_description' => $this->faker->paragraphs(3, true),
            'offer_price' => $this->faker->randomFloat(2, 10, 1000),
            'our_similar_offer' => $this->faker->word(),
            'their_differentiator' => $this->faker->paragraph(),
            'last_check' => $this->faker->date(),
            'status' => $this->faker->word(),
            'currency' => 'RON',
            'price_basis' => 'monthly',
            'source_url' => 'https://example.test/offers/sample',
            'observed_at' => now(),
        ];
    }
}
