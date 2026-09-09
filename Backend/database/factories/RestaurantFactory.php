<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manager_id' => User::factory()->create([
                'role' => 'restaurant_manager',
            ])->id,
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(8, 14),
            'longitude' => fake()->longitude(38, 42),
            'phone' => fake()->unique()->numerify('09########'),
            'logo' => null,
            'approval_status' => 'pending',
            'status' => 'inactive',
        ];
    }
}