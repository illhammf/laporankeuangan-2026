<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel transaksi keuangan.
     */
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relasi pengguna
            |--------------------------------------------------------------------------
            |
            | Setiap transaksi dimiliki oleh satu pengguna.
            |
            */
            $table->foreignId('pengguna_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Relasi dompet
            |--------------------------------------------------------------------------
            |
            | Menentukan dompet, rekening bank, atau e-wallet yang digunakan
            | dalam transaksi.
            |
            | Contoh:
            | - Cash
            | - BRI
            | - DANA
            | - GoPay
            | - ShopeePay
            | - Kebulan
            |
            */
            $table->foreignId('dompet_id')
                ->constrained('dompet')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Relasi kategori
            |--------------------------------------------------------------------------
            |
            | Menentukan kategori pemasukan atau pengeluaran.
            |
            */
            $table->foreignId('kategori_id')
                ->constrained('kategori')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identitas transaksi
            |--------------------------------------------------------------------------
            |
            | Kode transaksi dibuat otomatis pada aplikasi.
            | Contoh: TRX-20260724-000001
            |
            */
            $table->string('kode_transaksi', 50)->unique();

            $table->enum('jenis_transaksi', [
                'pemasukan',
                'pengeluaran',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Informasi transaksi
            |--------------------------------------------------------------------------
            */
            $table->dateTime('tanggal_transaksi');

            $table->string('nama_transaksi', 150);

            $table->decimal('nominal', 18, 2);

            $table->text('catatan')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Informasi pihak terkait
            |--------------------------------------------------------------------------
            |
            | Digunakan untuk menyimpan nama toko, pemberi uang, pelanggan,
            | perusahaan, atau pihak lain yang berhubungan dengan transaksi.
            |
            */
            $table->string('pihak_terkait', 150)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Informasi lokasi
            |--------------------------------------------------------------------------
            */
            $table->string('lokasi', 255)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Bukti transaksi
            |--------------------------------------------------------------------------
            |
            | Menyimpan lokasi file bukti pembayaran, nota, atau foto struk.
            |
            */
            $table->string('bukti_transaksi', 255)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status transaksi
            |--------------------------------------------------------------------------
            |
            | selesai:
            | Transaksi telah tercatat dan memengaruhi perhitungan keuangan.
            |
            | tertunda:
            | Transaksi masih menunggu penyelesaian.
            |
            | dibatalkan:
            | Transaksi tidak dihitung dalam laporan keuangan.
            |
            */
            $table->enum('status', [
                'selesai',
                'tertunda',
                'dibatalkan',
            ])->default('selesai');

            /*
            |--------------------------------------------------------------------------
            | Sumber pencatatan
            |--------------------------------------------------------------------------
            |
            | manual:
            | Transaksi dimasukkan langsung oleh pengguna.
            |
            | otomatis:
            | Transaksi dibuat melalui fitur transaksi rutin.
            |
            | impor:
            | Transaksi berasal dari proses impor data.
            |
            */
            $table->enum('sumber_pencatatan', [
                'manual',
                'otomatis',
                'impor',
            ])->default('manual');

            /*
            |--------------------------------------------------------------------------
            | Penanda transaksi rutin
            |--------------------------------------------------------------------------
            |
            | Kolom ini dapat digunakan untuk menandai transaksi yang berasal
            | dari pembayaran atau pemasukan berulang.
            |
            */
            $table->boolean('transaksi_rutin')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Timestamp dan soft delete
            |--------------------------------------------------------------------------
            */
            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Index laporan dan pencarian
            |--------------------------------------------------------------------------
            */

            // Laporan transaksi berdasarkan pengguna dan periode.
            $table->index(
                ['pengguna_id', 'tanggal_transaksi'],
                'transaksi_pengguna_tanggal_index'
            );

            // Laporan pemasukan dan pengeluaran.
            $table->index(
                [
                    'pengguna_id',
                    'jenis_transaksi',
                    'tanggal_transaksi',
                ],
                'transaksi_pengguna_jenis_tanggal_index'
            );

            // Riwayat transaksi setiap dompet.
            $table->index(
                [
                    'pengguna_id',
                    'dompet_id',
                    'tanggal_transaksi',
                ],
                'transaksi_pengguna_dompet_tanggal_index'
            );

            // Laporan pengeluaran atau pemasukan berdasarkan kategori.
            $table->index(
                [
                    'pengguna_id',
                    'kategori_id',
                    'tanggal_transaksi',
                ],
                'transaksi_pengguna_kategori_tanggal_index'
            );

            // Pencarian berdasarkan status transaksi.
            $table->index(
                [
                    'pengguna_id',
                    'status',
                    'tanggal_transaksi',
                ],
                'transaksi_pengguna_status_tanggal_index'
            );
        });
    }

    /**
     * Menghapus tabel transaksi.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};