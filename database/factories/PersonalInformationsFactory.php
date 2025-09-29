<?php

namespace Database\Factories;

use App\Models\PersonalInformations;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PersonalInformationsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PersonalInformations::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'civility' => $this->faker->title,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'maiden_name' => $this->faker->lastName,
            'birth_date' => $this->faker->date(),
            'martial_status' => $this->faker->word,
            'children_number' => $this->faker->randomNumber(),
            'mobile_number' => $this->faker->phoneNumber,
            'office_number' => $this->faker->phoneNumber,
            'personal_address' => $this->faker->address,
            'personal_address_2' => $this->faker->address,
            'personal_zip_code' => $this->faker->randomNumber(),
            'personal_city' => $this->faker->city,
            'personal_country' => $this->faker->country,
            'society_name' => $this->faker->name,
            'society_address' => $this->faker->address,
            'society_address_2' => $this->faker->address,
            'society_zip_code' => $this->faker->randomNumber(),
            'society_city' => $this->faker->city,
            'society_country' => $this->faker->country,
            'user_id' => $this->faker->unique()->randomNumber(),
            'secu_social' => $this->faker->secu_social,
            'secu_social_key' => $this->faker->secu_social_key,
        ];
    }
}
