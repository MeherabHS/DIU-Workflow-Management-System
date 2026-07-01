<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subtask>
 */
class SubtaskFactory extends Factory
{
    protected $model = Subtask::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
            'status' => 'pending',
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'deadline' => fake()->dateTimeBetween('+1 day', '+2 weeks')->format('Y-m-d'),
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }

    public function forTask(?Task $task = null): static
    {
        return $this->state(function () use ($task): array {
            $task ??= Task::factory()->create();

            return [
                'project_id' => $task->project_id,
                'task_id' => $task->id,
            ];
        });
    }
}