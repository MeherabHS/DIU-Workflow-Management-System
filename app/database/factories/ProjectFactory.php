<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'department_id' => Department::factory(),
            'created_by' => User::factory(),
            'status' => 'planned',
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'start_date' => fake()->dateTimeBetween('-1 week', '+1 week')->format('Y-m-d'),
            'deadline' => fake()->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'submitted_at' => null,
            'completed_at' => null,
            'archived_at' => null,
        ];
    }
}
