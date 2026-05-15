<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Bubble>
 */
class BubbleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label'   => '#' . fake()->word(),
            'color'   => fake()->hexColor(),
            'x'       => fake()->randomFloat(2, 50, 1200),
            'y'       => fake()->randomFloat(2, 50, 800),
            'size'    => fake()->numberBetween(60, 180),
        ];
    }
}
