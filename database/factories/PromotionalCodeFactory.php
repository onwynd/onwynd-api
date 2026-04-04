<?php

namespace Database\Factories;

use App\Models\PromotionalCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromotionalCodeFactory extends Factory
{
    protected $model = PromotionalCode::class;

    public function definition(): array
    {
        return [
            'uuid'              => (string) Str::uuid(),
            'code'              => strtoupper($this->faker->unique()->bothify('??????##')),
            'description'       => $this->faker->sentence(),
            'type'              => $this->faker->randomElement(['percentage', 'fixed']),
            'discount_value'    => $this->faker->numberBetween(5, 50),
            'currency'          => null,
            'max_uses'          => null,
            'uses_count'        => 0,
            'max_uses_per_user' => null,
            'valid_from'        => null,
            'valid_until'       => null,
            'applies_to'        => 'all',
            'is_active'         => true,
        ];
    }
}
