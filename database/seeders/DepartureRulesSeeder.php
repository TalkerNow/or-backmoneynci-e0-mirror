<?php

namespace Database\Seeders;

use App\Models\DepartureRule;
use Illuminate\Database\Seeder;

class DepartureRulesSeeder extends Seeder
{
    public function run(): void
    {
        if (DepartureRule::count() > 0) {
            $this->command->info('departure_rules already seeded — skipping.');
            return;
        }

        $rows = [
            ['sort_order' => 1,  'key_max' => 194812, 'age_months' => 720, 'trim' => 160, 'is_default' => false],
            ['sort_order' => 2,  'key_max' => 194912, 'age_months' => 720, 'trim' => 161, 'is_default' => false],
            ['sort_order' => 3,  'key_max' => 195012, 'age_months' => 720, 'trim' => 162, 'is_default' => false],
            ['sort_order' => 4,  'key_max' => 195106, 'age_months' => 720, 'trim' => 163, 'is_default' => false],
            ['sort_order' => 5,  'key_max' => 195112, 'age_months' => 724, 'trim' => 163, 'is_default' => false],
            ['sort_order' => 6,  'key_max' => 195212, 'age_months' => 729, 'trim' => 164, 'is_default' => false],
            ['sort_order' => 7,  'key_max' => 195312, 'age_months' => 734, 'trim' => 165, 'is_default' => false],
            ['sort_order' => 8,  'key_max' => 195412, 'age_months' => 739, 'trim' => 165, 'is_default' => false],
            ['sort_order' => 9,  'key_max' => 195712, 'age_months' => 744, 'trim' => 166, 'is_default' => false],
            ['sort_order' => 10, 'key_max' => 196012, 'age_months' => 744, 'trim' => 167, 'is_default' => false],
            ['sort_order' => 11, 'key_max' => 196108, 'age_months' => 744, 'trim' => 168, 'is_default' => false],
            ['sort_order' => 12, 'key_max' => 196112, 'age_months' => 747, 'trim' => 169, 'is_default' => false],
            ['sort_order' => 13, 'key_max' => 196212, 'age_months' => 750, 'trim' => 169, 'is_default' => false],
            ['sort_order' => 14, 'key_max' => 196312, 'age_months' => 753, 'trim' => 170, 'is_default' => false], // 1963 : 62a9m / 170
            ['sort_order' => 15, 'key_max' => 196412, 'age_months' => 756, 'trim' => 171, 'is_default' => false], // 1964 : 63a0m / 171 (corrige : etait 753/170)
            ['sort_order' => 16, 'key_max' => 196503, 'age_months' => 759, 'trim' => 172, 'is_default' => false], // 1965 : 63a3m / 172 (corrige : le split avril etait errone)
            ['sort_order' => 17, 'key_max' => 196512, 'age_months' => 759, 'trim' => 172, 'is_default' => false], // 1965 : 63a3m / 172 (corrige : etait 756/171)
            ['sort_order' => 18, 'key_max' => 196612, 'age_months' => 762, 'trim' => 172, 'is_default' => false], // 1966 : 63a6m (corrige : etait 759)
            ['sort_order' => 19, 'key_max' => 196712, 'age_months' => 765, 'trim' => 172, 'is_default' => false], // 1967 : 63a9m (corrige : etait 762)
            ['sort_order' => 20, 'key_max' => 196812, 'age_months' => 768, 'trim' => 172, 'is_default' => false], // 1968 : 64a0m (corrige : etait 765)
            ['sort_order' => 21, 'key_max' => null,   'age_months' => 768, 'trim' => 172, 'is_default' => true],  // 1969+ : 64a0m / 172
        ];

        foreach ($rows as $row) {
            DepartureRule::create($row);
        }
    }
}
