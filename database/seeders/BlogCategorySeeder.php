<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogCategorySeeder extends Seeder
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
            BlogCategory::firstOrCreate(
                ['slug' => Str::slug($cat)],
                ['name' => $cat]
            );
        }
    }
}
