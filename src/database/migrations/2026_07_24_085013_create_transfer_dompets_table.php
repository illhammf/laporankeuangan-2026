<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel transfer antar-dompet.
     */
    public function up(): void
    {
        Schema::create('transfer_dompet', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relasi pengguna
            |--------------------------------------------------------------------------
            |
            | Menentukan pemilik transaksi transfer.
            |
            */
            $table->foreignId('pengguna_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Dompet asal
            |--------------------------------------------------------------------------
            |
            | Dompet yang saldonya akan dikurangi.
            |
            */
            $table->foreignId('dompet_asal_id')
                ->constrained('dompet')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Dompet tujuan
            |--------------------------------------------------------------------------
            |
            | Dompet yang saldonya akan bertambah.
            |
            */
            $table->foreignId('dompet_tujuan_id')
                ->constrained('dompet')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identitas transfer
            |--------------------------------------------------------------------------
            |
            | Contoh kode:
            | TRF-20260724-000001
            |
            */
            $table->string('kode_transfer', 50)->unique();

            /*
            |--------------------------------------------------------------------------
            | Informasi transfer
            |--------------------------------------------------------------------------
            */
            $table->dateTime('tanggal_transfer');

            $table->decimal('nominal', 18, 2);

            /*
            |--------------------------------------------------------------------------
            | Biaya administrasi
            |--------------------------------------------------------------------------
            |
            | Biaya administrasi hanya mengurangi saldo dompet asal.
            |
            | Contoh:
            | Transfer BRI ke DANA       = Rp100.000
            | Biaya administrasi         = Rp2.500
            | Saldo BRI berkurang        = Rp102.500
            | Saldo DANA bertambah       = Rp100.000
            |
            */
            $table->decimal('biaya_admin', 18, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Informasi tambahan
            |--------------------------------------------------------------------------
            */
            $table->text('catatan')->nullable();

            $table->string('bukti_transfer', 255)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status transfer
            |--------------------------------------------------------------------------
            |
            | selesai:
            | Transfer sudah berhasil dan memengaruhi saldo.
            |
            | tertunda:
            | Transfer masih menunggu penyelesaian.
            |
            | gagal:
            | Transfer gagal dan tidak memengaruhi saldo.
            |
            | dibatalkan:
            | Transfer dibatalkan dan tidak memengaruhi saldo.
            |
            */
            $table->enum('status', [
                'selesai',
                'tertunda',
                'gagal',
                'dibatalkan',
            ])->default('selesai');

            /*
            |--------------------------------------------------------------------------
            | Sumber pencatatan
            |--------------------------------------------------------------------------
            */
            $table->enum('sumber_pencatatan', [
                'manual',
                'otomatis',
                'impor',
            ])->default('manual');

            /*
            |--------------------------------------------------------------------------
            | Waktu penyelesaian
            |--------------------------------------------------------------------------
            |
            | Menyimpan waktu ketika transfer benar-benar berhasil.
            |
            */
            $table->dateTime('diselesaikan_pada')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timestamp dan soft delete
            |--------------------------------------------------------------------------
            */
            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Index
            |--------------------------------------------------------------------------
            */

            // Riwayat transfer pengguna berdasarkan tanggal.
            $table->index(
                ['pengguna_id', 'tanggal_transfer'],
                'transfer_pengguna_tanggal_index'
            );

            // Riwayat transfer keluar dari suatu dompet.
            $table->index(
                ['pengguna_id', 'dompet_asal_id', 'tanggal_transfer'],
                'transfer_pengguna_asal_tanggal_index'
            );

            // Riwayat transfer masuk ke suatu dompet.
            $table->index(
                ['pengguna_id', 'dompet_tujuan_id', 'tanggal_transfer'],
                'transfer_pengguna_tujuan_tanggal_index'
            );

            // Pencarian transfer berdasarkan status.
            $table->index(
                ['pengguna_id', 'status', 'tanggal_transfer'],
                'transfer_pengguna_status_tanggal_index'
            );
        });
    }

    /**
     * Menghapus tabel transfer antar-dompet.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_dompet');
    }
};