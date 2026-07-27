<?php

namespace Database\Seeders;

use App\Models\Dompet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DompetSeeder extends Seeder
{
    /**
     * Membuat dompet awal untuk seluruh pengguna yang sudah tersedia.
     *
     * Seeder bersifat idempotent:
     * - Tidak membuat data duplikat.
     * - Memperbarui data dompet jika seeder dijalankan ulang.
     * - Memulihkan dompet yang sebelumnya dihapus menggunakan soft delete.
     */
    public function run(): void
    {
        $penggunaList = User::query()->get();

        if ($penggunaList->isEmpty()) {
            $this->command?->warn(
                'DompetSeeder dilewati karena belum ada pengguna pada tabel users.'
            );

            return;
        }

        $daftarDompet = [
            [
                'nama_dompet' => 'Cash',
                'jenis_dompet' => 'tunai',
                'nomor_akun' => null,
                'saldo_awal' => 0,
                'mata_uang' => 'IDR',
                'ikon' => 'heroicon-o-banknotes',
                'warna' => '#16A34A',
                'urutan' => 1,
                'aktif' => true,
                'catatan' => 'Uang tunai yang disimpan di dompet.',
            ],
            [
                'nama_dompet' => 'BRI',
                'jenis_dompet' => 'bank',
                'nomor_akun' => null,
                'saldo_awal' => 0,
                'mata_uang' => 'IDR',
                'ikon' => 'heroicon-o-building-library',
                'warna' => '#00529C',
                'urutan' => 2,
                'aktif' => true,
                'catatan' => 'Rekening Bank Rakyat Indonesia.',
            ],
            [
                'nama_dompet' => 'DANA',
                'jenis_dompet' => 'dompet_digital',
                'nomor_akun' => null,
                'saldo_awal' => 0,
                'mata_uang' => 'IDR',
                'ikon' => 'heroicon-o-device-phone-mobile',
                'warna' => '#118EEA',
                'urutan' => 3,
                'aktif' => true,
                'catatan' => 'Saldo dompet digital DANA.',
            ],
            [
                'nama_dompet' => 'GoPay',
                'jenis_dompet' => 'dompet_digital',
                'nomor_akun' => null,
                'saldo_awal' => 0,
                'mata_uang' => 'IDR',
                'ikon' => 'heroicon-o-device-phone-mobile',
                'warna' => '#00AED6',
                'urutan' => 4,
                'aktif' => true,
                'catatan' => 'Saldo dompet digital GoPay.',
            ],
            [
                'nama_dompet' => 'ShopeePay',
                'jenis_dompet' => 'dompet_digital',
                'nomor_akun' => null,
                'saldo_awal' => 0,
                'mata_uang' => 'IDR',
                'ikon' => 'heroicon-o-device-phone-mobile',
                'warna' => '#EE4D2D',
                'urutan' => 5,
                'aktif' => true,
                'catatan' => 'Saldo dompet digital ShopeePay.',
            ],
            [
                'nama_dompet' => 'Kebulan',
                'jenis_dompet' => 'lainnya',
                'nomor_akun' => null,
                'saldo_awal' => 0,
                'mata_uang' => 'IDR',
                'ikon' => 'heroicon-o-wallet',
                'warna' => '#7C3AED',
                'urutan' => 6,
                'aktif' => true,
                'catatan' => 'Dompet Kebulan.',
            ],
        ];

        DB::transaction(function () use (
            $penggunaList,
            $daftarDompet
        ): void {
            foreach ($penggunaList as $pengguna) {
                foreach ($daftarDompet as $dataDompet) {
                    $dompet = Dompet::withTrashed()
                        ->where('pengguna_id', $pengguna->id)
                        ->where(
                            'nama_dompet',
                            $dataDompet['nama_dompet']
                        )
                        ->first();

                    if ($dompet === null) {
                        $dompet = new Dompet();
                    }

                    $dompet->fill([
                        'pengguna_id' => $pengguna->id,
                        ...$dataDompet,
                    ]);

                    if ($dompet->trashed()) {
                        $dompet->restore();
                    }

                    $dompet->save();
                }
            }
        });

        $jumlahDompet = $penggunaList->count()
            * count($daftarDompet);

        $this->command?->info(
            "{$jumlahDompet} data dompet berhasil dibuat atau diperbarui."
        );
    }
}