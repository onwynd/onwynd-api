<?php

namespace Database\Seeders;

use App\Models\ChatCategory;
use Illuminate\Database\Seeder;

class ChatCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['slug' => 'current', 'label' => 'Current', 'icon' => 'grid', 'count' => 12, 'is_active' => false, 'sort_order' => 1],
            ['slug' => 'bookmark', 'label' => 'Bookmark', 'icon' => 'star-outline', 'count' => 25, 'is_active' => false, 'sort_order' => 2],
            ['slug' => 'favorites', 'label' => 'Favorites', 'icon' => 'folder', 'count' => 999, 'is_active' => false, 'sort_order' => 3],
            ['slug' => 'trash', 'label' => 'Trash', 'icon' => 'trash', 'count' => 8, 'is_active' => false, 'sort_order' => 4],
            ['slug' => 'unassigned', 'label' => 'Unassigned', 'icon' => 'help-circle', 'count' => 1, 'is_active' => true, 'sort_order' => 5],
            ['slug' => 'other', 'label' => 'Other', 'icon' => 'eye', 'count' => 25, 'is_active' => false, 'sort_order' => 6],
            ['slug' => 'mystery', 'label' => 'Mystery', 'icon' => 'chat-options', 'count' => 664, 'is_active' => false, 'sort_order' => 7],
            ['slug' => 'happiness', 'label' => 'Happiness', 'icon' => 'smile', 'count' => 88, 'is_active' => false, 'sort_order' => 8],
        ];

        ChatCategory::query()->truncate();
        foreach ($rows as $row) {
            ChatCategory::create($row);
        }
    }
}
