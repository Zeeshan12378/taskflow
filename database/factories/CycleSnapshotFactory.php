<?php

namespace Database\Factories;

use App\Models\Cycle;
use App\Models\CycleSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CycleSnapshot>
 */
class CycleSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalTasks = fake()->numberBetween(0, 30);
        $totalStoryPoints = fake()->numberBetween(0, 100);

        return [
            'cycle_id' => Cycle::factory(),
            'snapshot_date' => fake()->date(),
            'snapshot_type' => fake()->randomElement(['start', 'daily', 'completion']),
            'total_tasks' => $totalTasks,
            'completed_tasks' => fake()->numberBetween(0, $totalTasks),
            'total_story_points' => $totalStoryPoints,
            'completed_story_points' => fake()->numberBetween(0, $totalStoryPoints),
        ];
    }
}
