<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel kategori transaksi.
     */
    public function up(): void
    {
        Schema::create('kategori', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relasi pengguna
            |--------------------------------------------------------------------------
            |
            | Setiap kategori dimiliki oleh satu pengguna.
            |
            */
            $table->foreignId('pengguna_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Relasi kategori induk
            |--------------------------------------------------------------------------
            |
            | Digunakan jika kategori memiliki subkategori.
            |
            | Contoh:
            | - Makanan dan Minuman
            |   - Makan Siang
            |   - Kopi
            |   - Jajan
            |
            */
            $table->foreignId('kategori_induk_id')
                ->nullable()
                ->constrained('kategori')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Informasi utama kategori
            |--------------------------------------------------------------------------
            */
            $table->string('nama_kategori', 100);

            $table->string('kode_kategori', 100)->nullable();

            $table->enum('jenis_transaksi', [
                'pemasukan',
                'pengeluaran',
            ]);

            $table->text('deskripsi')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Pengaturan tampilan
            |--------------------------------------------------------------------------
            */
            $table->string('ikon', 100)->nullable();

            $table->string('warna', 20)->nullable();

            $table->unsignedSmallInteger('urutan')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Pengaturan kategori
            |--------------------------------------------------------------------------
            |
            | kategori_bawaan:
            | Menandakan kategori dibuat otomatis melalui seeder.
            |
            | aktif:
            | Menentukan apakah kategori masih dapat dipilih ketika membuat
            | transaksi baru.
            |
            */
            $table->boolean('kategori_bawaan')->default(false);

            $table->boolean('aktif')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Timestamp dan soft delete
            |--------------------------------------------------------------------------
            */
            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Constraint dan index
            |--------------------------------------------------------------------------
            |
            | Satu pengguna tidak dapat memiliki nama kategori yang sama
            | untuk jenis transaksi yang sama.
            |
            | Contoh yang diperbolehkan:
            | - Lainnya untuk pemasukan
            | - Lainnya untuk pengeluaran
            |
            */
            $table->unique(
                [
                    'pengguna_id',
                    'jenis_transaksi',
                    'nama_kategori',
                ],
                'kategori_pengguna_jenis_nama_unique'
            );

            /*
            | Index untuk pencarian kategori berdasarkan pengguna,
            | jenis transaksi, dan status aktif.
            */
            $table->index(
                [
                    'pengguna_id',
                    'jenis_transaksi',
                    'aktif',
                ],
                'kategori_pengguna_jenis_aktif_index'
            );

            /*
            | Index untuk menampilkan subkategori.
            */
            $table->index(
                [
                    'pengguna_id',
                    'kategori_induk_id',
                ],
                'kategori_pengguna_induk_index'
            );

            /*
            | Index untuk pengurutan tampilan kategori.
            */
            $table->index(
                [
                    'pengguna_id',
                    'jenis_transaksi',
                    'urutan',
                ],
                'kategori_pengguna_jenis_urutan_index'
            );
        });
    }

    /**
     * Menghapus tabel kategori.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori');
    }
};