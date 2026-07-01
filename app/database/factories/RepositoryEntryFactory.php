<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepositoryEntry>
 */
class RepositoryEntryFactory extends Factory
{
    protected $model = RepositoryEntry::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(['Internal', 'Tender', 'Admin']),
            'department_id' => Department::factory(),
            'client_or_office' => fake()->company(),
            'responsible_user_id' => User::factory(),
            'status' => 'planned',
            'deadline' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'value_amount' => fake()->randomFloat(2, 1000, 50000),
            'value_currency' => 'BDT',
            'description' => fake()->paragraph(),
            'submitted_at' => null,
            'completed_at' => null,
            'archived_at' => null,
            'created_by' => User::factory(),
        ];
    }
}