<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Permissions globales — pas de dépendance tenant
            PermissionSeeder::class,

            // 2. Organisation de test + rôles + utilisateurs
            //    Dépend de : PermissionSeeder
            OrganisationSeeder::class,
        ]);
    }
}
