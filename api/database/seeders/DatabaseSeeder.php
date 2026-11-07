<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Só dados fictícios e só no dev (OIK-ADR-0011 §4): conta sem 2FA para testar o login. */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        DB::table('contas')->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'email' => 'demo@oikora.test',
            'celular' => '+5511999990000',
            'password' => Hash::make('oikora123'),
            'estado' => 'ativa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
