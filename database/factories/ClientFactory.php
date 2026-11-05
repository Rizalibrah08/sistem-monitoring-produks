<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'company' => fake()->company(),
            'pic_name' => fake()->name(),
            'whatsapp' => '628'.fake()->unique()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'marketing_user_id' => User::factory(),
        ];
    }
}
