<?php

namespace Database\Seeders;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Seeder;

class NewsletterSubscriberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // <architect:seed>
        NewsletterSubscriber::factory()->count(10)->create();
        // </architect:seed>
    }
}