<?php

namespace Database\Factories;

use App\Models\DisciplinaryAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplinaryAction>
 */
class DisciplinaryActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => User::ROLE_OPERATOR]),
            'actor_id' => null,
            'kind' => DisciplinaryAction::KIND_VERBAL_WARNING,
            'note' => fake()->sentence(),
            'warning_subject' => null,
            'email_sent_at' => null,
        ];
    }
}
