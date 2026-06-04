<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Role::DEFINITIONS as $slug => $attributes) {
            Role::query()->updateOrCreate(
                ['slug' => $slug],
                $attributes,
            );
        }
    }
}
