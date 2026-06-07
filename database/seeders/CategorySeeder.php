<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Seed the product categories for the Ourth marketplace.
     * Uses updateOrInsert so existing dashboard-created categories are preserved.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Plates',    'sort_order' => 1],
            ['name' => 'Bowls',     'sort_order' => 2],
            ['name' => 'Platters',  'sort_order' => 3],
            ['name' => 'Cutlery',   'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['slug' => Str::slug($category['name'])],
                [
                    'name'       => $category['name'],
                    'sort_order' => $category['sort_order'],
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Categories seeded (' . count($categories) . ' records).');
    }
}
