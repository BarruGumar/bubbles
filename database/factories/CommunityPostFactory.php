<?php

namespace Database\Factories;

use App\Models\Bubble;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommunityPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bubble_id' => Bubble::factory(),
            'user_id'   => User::factory(),
            'content'   => fake()->paragraph(),
        ];
    }
}
