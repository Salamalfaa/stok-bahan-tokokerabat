<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement(array_keys(Expense::CATEGORIES)),
            'description' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, 1, 1000000),
            'transaction_date' => today()->toDateString(),
        ];
    }
}
