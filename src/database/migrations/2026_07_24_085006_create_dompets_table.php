<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel dompet.
     */
    public function up(): void
    {
        Schema::create('dompet', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relasi pengguna
            |--------------------------------------------------------------------------
            |
            | Setiap dompet dimiliki oleh satu pengguna.
            | Data dompet akan ikut terhapus ketika pengguna dihapus.
            |
            */
            $table->foreignId('pengguna_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Informasi utama dompet
            |--------------------------------------------------------------------------
            */
            $table->string('nama_dompet', 100);

            $table->enum('jenis_dompet', [
                'tunai',
                'bank',
                'dompet_digital',
                'lainnya',
            ])->default('lainnya');

            /*
            |--------------------------------------------------------------------------
            | Informasi rekening atau akun
            |--------------------------------------------------------------------------
            |
            | Digunakan untuk nomor rekening bank atau nomor akun e-wallet.
            | Bersifat opsional karena dompet tunai tidak memerlukannya.
            |
            */
            $table->string('nomor_akun', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Informasi saldo
            |--------------------------------------------------------------------------
            |
            | Saldo awal adalah saldo ketika dompet pertama kali didaftarkan.
            | Saldo saat ini nantinya dihitung dari seluruh transaksi.
            |
            */
            $table->decimal('saldo_awal', 18, 2)->default(0);

            $table->char('mata_uang', 3)->default('IDR');

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
            | Status dan keterangan
            |--------------------------------------------------------------------------
            */
            $table->boolean('aktif')->default(true);
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
            | Constraint dan index
            |--------------------------------------------------------------------------
            |
            | Satu pengguna tidak boleh memiliki dua dompet dengan nama sama.
            |
            */
            $table->unique(
                ['pengguna_id', 'nama_dompet'],
                'dompet_pengguna_nama_unique'
            );

            $table->index(
                ['pengguna_id', 'aktif'],
                'dompet_pengguna_aktif_index'
            );

            $table->index(
                ['pengguna_id', 'jenis_dompet'],
                'dompet_pengguna_jenis_index'
            );

            $table->index(
                ['pengguna_id', 'urutan'],
                'dompet_pengguna_urutan_index'
            );
        });
    }

    /**
     * Menghapus tabel dompet.
     */
    public function down(): void
    {
        Schema::dropIfExists('dompet');
    }
};