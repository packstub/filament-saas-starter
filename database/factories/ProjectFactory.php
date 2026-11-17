<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->catchPhrase(),
            'status' => ProjectStatus::Active,
        ];
    }

    public function paused(): static
    {
        return $this->state(['status' => ProjectStatus::Paused]);
    }

    public function done(): static
    {
        return $this->state(['status' => ProjectStatus::Done]);
    }
}
