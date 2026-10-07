<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Gustavo',
            'email' => 'admin@gestoque.test',
            'password' => 'password',
            'perfil' => 'administrador',
        ]);

        User::factory()->create([
            'name' => 'Pedro',
            'email' => 'pedro@gestoque.test',
            'password' => 'password',
            'perfil' => 'mecanico',
        ]);

        $this->call(ProdutoSeeder::class);
    }
}
