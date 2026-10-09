<?php

namespace Database\Seeders;

use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Seller::factory()->create([
            'name' => 'Test Seller',
            'email' => 'test@example.com',
        ]);
    }
}
