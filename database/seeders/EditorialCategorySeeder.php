<?php

namespace Database\Seeders;

use App\Models\EditorialCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EditorialCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Mental Health',
            'Therapy',
            'Self-Care',
            'Relationships',
            'Mindfulness',
            'Anxiety',
            'Depression',
            'Wellness',
        ];

        foreach ($categories as $cat) {
            EditorialCategory::firstOrCreate(
                ['slug' => Str::slug($cat)],
                ['name' => $cat]
            );
        }
    }
}
