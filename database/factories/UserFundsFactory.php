<?php

namespace Database\Factories;

use App\Models\UserFunds;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFundsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UserFunds::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'amount' => $this->faker->numberBetween(125000, 2000000000),
            'risk' => $this->faker->numberBetween(1, 3),
            'user_id' => $this->faker->numberBetween(0, 1000),
        ];
    }
}
