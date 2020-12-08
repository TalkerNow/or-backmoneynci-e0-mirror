<?php

namespace Database\Factories;

use App\Models\Services;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServicesFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Services::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->realText(10),
            'variable' => $this->faker->words(1, true),
            'value' => $this->faker->numberBetween(0, 100),
            'variable1' => $this->faker->words(1, true),
            'value1' => $this->faker->numberBetween(0, 100),
            'total_ht' => $this->faker->numberBetween(0, 1000),
            'total_ttc' => $this->faker->numberBetween(0, 1000),
            'tva' => $this->faker->numberBetween(0, 70),
            'status' => $this->faker->randomElement(['template', 'selected', 'unselected']),
            'document_id' => $this->faker->numberBetween(0, 1000),
            'parent_id' => $this->faker->numberBetween(0, 1000)
        ];
    }
}