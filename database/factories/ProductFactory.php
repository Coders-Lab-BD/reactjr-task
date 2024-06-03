<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->firstName(),
            'brand' => fake()->company(),
            'type' => Arr::random(['Mug', 'Jug', 'Cup', 'Glass', 'Plate']),
            'origin' => fake()->country(),
        ];
    }
}
