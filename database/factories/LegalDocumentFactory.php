<?php

namespace Database\Factories;

use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LegalDocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<LegalDocument>
     */
    protected $model = LegalDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'category' => $this->faker->word(),
            'expiration_date' => $this->faker->date(),
            'physical_location' => $this->faker->word(),
            'document' => $this->faker->word(),
        ];
    }
}