<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
            'assigned_to' => null,
            'status' => 'pending',
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'deadline' => fake()->dateTimeBetween('+1 day', '+3 weeks')->format('Y-m-d'),
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }
}