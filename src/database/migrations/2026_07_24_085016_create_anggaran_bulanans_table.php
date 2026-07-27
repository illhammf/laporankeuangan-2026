<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel anggaran bulanan.
     */
    public function up(): void
    {
        Schema::create('anggaran_bulanan', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relasi pengguna
            |--------------------------------------------------------------------------
            |
            | Setiap anggaran dimiliki oleh satu pengguna.
            |
            */
            $table->foreignId('pengguna_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Relasi kategori
            |--------------------------------------------------------------------------
            |
            | Anggaran dibuat berdasarkan kategori pengeluaran.
            |
            | Contoh:
            | - Makanan dan Minuman
            | - Transportasi
            | - Belanja
            | - Hiburan
            |
            */
            $table->foreignId('kategori_id')
                ->constrained('kategori')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Periode anggaran
            |--------------------------------------------------------------------------
            |
            | Bulan menggunakan angka 1 sampai 12.
            | Tahun menggunakan empat digit, misalnya 2026.
            |
            */
            $table->unsignedTinyInteger('bulan');

            $table->unsignedSmallInteger('tahun');

            /*
            |--------------------------------------------------------------------------
            | Nilai anggaran
            |--------------------------------------------------------------------------
            |
            | Contoh:
            | Anggaran makanan Juli 2026 = Rp1.000.000
            |
            */
            $table->decimal('nominal_anggaran', 18, 2);

            /*
            |--------------------------------------------------------------------------
            | Batas peringatan
            |--------------------------------------------------------------------------
            |
            | Sistem dapat memberikan peringatan ketika penggunaan anggaran
            | mencapai persentase tertentu.
            |
            | Contoh:
            | nominal_anggaran   = Rp1.000.000
            | batas_peringatan   = 80
            |
            | Peringatan muncul ketika pengeluaran mencapai Rp800.000.
            |
            */
            $table->decimal('batas_peringatan', 5, 2)->default(80);

            /*
            |--------------------------------------------------------------------------
            | Opsi perpanjangan anggaran
            |--------------------------------------------------------------------------
            |
            | Jika aktif, anggaran dapat digunakan sebagai acuan untuk membuat
            | anggaran pada bulan berikutnya.
            |
            */
            $table->boolean('ulangi_bulan_berikutnya')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Status anggaran
            |--------------------------------------------------------------------------
            |
            | aktif:
            | Anggaran digunakan dalam pemantauan pengeluaran.
            |
            | selesai:
            | Periode anggaran telah berakhir.
            |
            | dibatalkan:
            | Anggaran tidak digunakan.
            |
            */
            $table->enum('status', [
                'aktif',
                'selesai',
                'dibatalkan',
            ])->default('aktif');

            /*
            |--------------------------------------------------------------------------
            | Informasi tambahan
            |--------------------------------------------------------------------------
            */
            $table->text('catatan')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timestamp dan soft delete
            |--------------------------------------------------------------------------
            */
            $table->timestamps();

            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Constraint unik
            |--------------------------------------------------------------------------
            |
            | Satu pengguna hanya boleh memiliki satu anggaran untuk kategori
            | yang sama pada bulan dan tahun yang sama.
            |
            */
            $table->unique(
                [
                    'pengguna_id',
                    'kategori_id',
                    'bulan',
                    'tahun',
                ],
                'anggaran_pengguna_kategori_periode_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Index
            |--------------------------------------------------------------------------
            */

            // Menampilkan seluruh anggaran pengguna pada periode tertentu.
            $table->index(
                [
                    'pengguna_id',
                    'tahun',
                    'bulan',
                ],
                'anggaran_pengguna_periode_index'
            );

            // Menampilkan riwayat anggaran berdasarkan kategori.
            $table->index(
                [
                    'pengguna_id',
                    'kategori_id',
                    'tahun',
                    'bulan',
                ],
                'anggaran_pengguna_kategori_index'
            );

            // Menampilkan anggaran berdasarkan status.
            $table->index(
                [
                    'pengguna_id',
                    'status',
                    'tahun',
                    'bulan',
                ],
                'anggaran_pengguna_status_periode_index'
            );
        });
    }

    /**
     * Menghapus tabel anggaran bulanan.
     */
    public function down(): void
    {
        Schema::dropIfExists('anggaran_bulanan');
    }
};