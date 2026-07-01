<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowMessage> */
class WorkflowMessageFactory extends Factory
{
    protected $model = WorkflowMessage::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'sender_id' => User::factory(),
            'message_type' => 'message',
            'body' => fake()->sentence(),
            'visibility' => 'thread',
        ];
    }
}
