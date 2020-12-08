<?php

namespace Database\Factories;

use App\Models\Documents;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Documents::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'link_to_documents' => $this->faker->url,
            'type' => $this->faker->randomElement(['docusign', 'contrat', 'pdf']),
            'document_state' => $this->faker->randomElement(['pending', 'accepted', 'refused']),
            'comment' => $this->faker->realText(10),
            'advanced_payment' => $this->faker->numberBetween(5, 8),
            'user_id' => $this->faker->unique()->randomNumber(),
        ];
    }
}
