<?php

namespace Database\Factories;

use App\Models\PromotionalCode;
use App\Models\PromotionalCodeUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionalCodeUsageFactory extends Factory
{
    protected $model = PromotionalCodeUsage::class;

    public function definition(): array
    {
        return [
            'promotional_code_id' => PromotionalCode::factory(),
            'user_id'             => User::factory(),
            'session_id'          => null,
            'discount_applied'    => $this->faker->randomFloat(2, 100, 5000),
        ];
    }
}
