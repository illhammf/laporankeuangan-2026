<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan seluruh seeder utama aplikasi.
     */
    public function run(): void
    {
        $this->call([
            /*
            |--------------------------------------------------------------------------
            | Seeder bawaan boilerplate
            |--------------------------------------------------------------------------
            |
            | Role harus dibuat lebih dahulu karena UserSeeder kemungkinan
            | memberikan role kepada pengguna.
            |
            */
            RoleSeeder::class,
            UserSeeder::class,

            /*
            |--------------------------------------------------------------------------
            | Seeder aplikasi laporan keuangan
            |--------------------------------------------------------------------------
            |
            | Dompet dan kategori dibuat setelah pengguna tersedia karena
            | keduanya memiliki foreign key pengguna_id ke tabel users.
            |
            */
            DompetSeeder::class,
            KategoriSeeder::class,
        ]);
    }
}