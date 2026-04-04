<?php

namespace Database\Factories;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition()
    {
        $title = $this->faker->sentence(3);
        $typeOptions = ['depression', 'anxiety', 'stress', 'general', 'ptsd', 'ocd'];
        $type = $this->faker->randomElement($typeOptions);

        return [
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->randomNumber(4),
            'description' => $this->faker->paragraph(),
            'type' => $type,
            'total_questions' => $this->faker->numberBetween(5, 20),
            'scoring_method' => json_encode(['method' => 'sum']),
            'interpretation_guide' => json_encode(['minimal' => [0, 4], 'mild' => [5, 9]]),
            'is_active' => true,
        ];
    }
}
