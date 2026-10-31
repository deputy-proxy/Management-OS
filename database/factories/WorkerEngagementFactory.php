<?php

namespace Database\Factories;

use App\Models\WorkerEngagement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkerEngagement> */
class WorkerEngagementFactory extends Factory
{
    protected $model = WorkerEngagement::class;

    public function definition(): array
    {
        return [
            'engagement_type' => 'unknown',
            'status' => 'unresolved',
            'start_date' => null,
            'end_date' => null,
            'source_reference' => null,
        ];
    }
}
