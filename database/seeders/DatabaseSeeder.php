<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Plans first: a seller subscription is meaningless without one.
        $this->call(PlanSeeder::class);

        // The shipped legal copy, so the public pages and acceptance checks have
        // a published version before an admin edits anything.
        $this->call(PolicySeeder::class);

        // Two demo sellers, one per theme, with history on every screen.
        $this->call(DemoSellerSeeder::class);
    }
}
