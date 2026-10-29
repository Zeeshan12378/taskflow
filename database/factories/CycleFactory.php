<?php

namespace Database\Factories;

use App\Models\Cycle;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cycle>
 */
class CycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'project_id' => null,
            'name' => fake()->unique()->words(2, true),
            'start_date' => today(),
            'end_date' => today()->addDays(13),
            'status' => 'planned',
        ];
    }
}
