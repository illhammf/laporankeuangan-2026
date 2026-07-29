<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Dompet;
use App\Models\Transaksi;
use App\Models\TransferDompet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaldoDompetWidget extends BaseWidget
{
    /**
     * Urutan widget pada dashboard.
     */
    protected static ?int $sort = 2;

    /**
     * Widget memenuhi lebar dashboard.
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Data diperbarui setiap 30 detik.
     */
    protected static ?string $pollingInterval = '30s';

    /**
     * Langsung dimuat saat dashboard dibuka.
     */
    protected static bool $isLazy = false;

    /**
     * Judul widget.
     */
    protected ?string $heading = 'Saldo per Dompet';

    /**
     * Deskripsi widget.
     */
    protected function getDescription(): ?string
    {
        return 'Saldo aktual setiap dompet aktif setelah pemasukan, '
            . 'pengeluaran, transfer, dan biaya administrasi hingga '
            . now('Asia/Jakarta')->format('d/m/Y H:i')
            . ' WIB.';
    }

    /**
     * Menampilkan satu kartu statistik untuk setiap dompet aktif.
     */
    protected function getStats(): array
    {
        $dompetList = $this->ambilDompetDenganSaldo();

        if ($dompetList->isEmpty()) {
            return [
                Stat::make(
                    'Saldo per Dompet',
                    'Belum ada dompet aktif'
                )
                    ->description(
                        'Tambahkan atau aktifkan dompet melalui menu Dompet.'
                    )
                    ->descriptionIcon('heroicon-m-wallet')
                    ->color('gray'),
            ];
        }

        $dataPerubahanHarian = $this->ambilPerubahanSaldoHarian(
            jumlahHari: 7
        );

        return $dompetList
            ->map(
                function (
                    Dompet $dompet
                ) use ($dataPerubahanHarian): Stat {
                    $saldoSaatIni = $this->hitungSaldoSaatIni(
                        $dompet
                    );

                    $perubahanHarian =
                        $this->susunPerubahanHarianDompet(
                            dompet: $dompet,
                            dataHarian: $dataPerubahanHarian,
                            jumlahHari: 7
                        );

                    $grafikSaldo =
                        $this->buatGrafikSaldoDompet(
                            saldoSaatIni: $saldoSaatIni,
                            perubahanHarian: $perubahanHarian
                        );

                    $perubahanTujuhHari = array_sum(
                        $perubahanHarian
                    );

                    $informasiPerubahan =
                        $this->buatInformasiPerubahan(
                            perubahan: $perubahanTujuhHari
                        );

                    $namaPemilik =
                        $dompet->pengguna?->name
                        ?? 'Pemilik tidak tersedia';

                    $emailPemilik =
                        $dompet->pengguna?->email
                        ?? '-';

                    $jenisDompet =
                        $this->labelJenisDompet(
                            $dompet->jenis_dompet
                        );

                    return Stat::make(
                        "{$dompet->nama_dompet} — {$namaPemilik}",
                        $this->formatRupiah($saldoSaatIni)
                    )
                        ->description(
                            "{$jenisDompet} • "
                            . $informasiPerubahan['deskripsi']
                        )
                        ->descriptionIcon(
                            $informasiPerubahan['ikon']
                        )
                        ->chart($grafikSaldo)
                        ->color(
                            $this->tentukanWarnaStat(
                                saldoSaatIni: $saldoSaatIni,
                                jenisDompet: $dompet
                                    ->jenis_dompet
                            )
                        )
                        ->extraAttributes([
                            'title' =>
                                "Pemilik: {$namaPemilik}"
                                . "\nEmail: {$emailPemilik}"
                                . "\nJenis: {$jenisDompet}"
                                . "\nSaldo awal: "
                                . $this->formatRupiah(
                                    (float) $dompet->saldo_awal
                                )
                                . "\nSaldo saat ini: "
                                . $this->formatRupiah(
                                    $saldoSaatIni
                                ),
                        ]);
                }
            )
            ->values()
            ->all();
    }

    /**
     * Mengambil seluruh dompet aktif beserta hasil agregasi
     * transaksi dan transfer.
     */
    private function ambilDompetDenganSaldo(): Collection
    {
        return Dompet::query()
            ->with('pengguna')
            ->where('aktif', true)
            ->withSum([
                'transaksi as total_pemasukan_selesai' =>
                    fn (Builder $query): Builder => $query
                        ->where(
                            'jenis_transaksi',
                            Transaksi::JENIS_PEMASUKAN
                        )
                        ->where(
                            'status',
                            Transaksi::STATUS_SELESAI
                        ),
            ], 'nominal')
            ->withSum([
                'transaksi as total_pengeluaran_selesai' =>
                    fn (Builder $query): Builder => $query
                        ->where(
                            'jenis_transaksi',
                            Transaksi::JENIS_PENGELUARAN
                        )
                        ->where(
                            'status',
                            Transaksi::STATUS_SELESAI
                        ),
            ], 'nominal')
            ->withSum([
                'transferMasuk as total_transfer_masuk_selesai' =>
                    fn (Builder $query): Builder => $query
                        ->where(
                            'status',
                            TransferDompet::STATUS_SELESAI
                        ),
            ], 'nominal')
            ->withSum([
                'transferKeluar as total_transfer_keluar_selesai' =>
                    fn (Builder $query): Builder => $query
                        ->where(
                            'status',
                            TransferDompet::STATUS_SELESAI
                        ),
            ], 'nominal')
            ->withSum([
                'transferKeluar as total_biaya_admin_transfer' =>
                    fn (Builder $query): Builder => $query
                        ->where(
                            'status',
                            TransferDompet::STATUS_SELESAI
                        ),
            ], 'biaya_admin')
            ->orderBy('pengguna_id')
            ->orderBy('urutan')
            ->orderBy('nama_dompet')
            ->get();
    }

    /**
     * Menghitung saldo aktual satu dompet.
     */
    private function hitungSaldoSaatIni(
        Dompet $dompet
    ): float {
        return (float) $dompet->saldo_awal
            + (float) (
                $dompet->total_pemasukan_selesai
                ?? 0
            )
            - (float) (
                $dompet->total_pengeluaran_selesai
                ?? 0
            )
            + (float) (
                $dompet->total_transfer_masuk_selesai
                ?? 0
            )
            - (float) (
                $dompet->total_transfer_keluar_selesai
                ?? 0
            )
            - (float) (
                $dompet->total_biaya_admin_transfer
                ?? 0
            );
    }

    /**
     * Mengambil perubahan saldo semua dompet untuk
     * beberapa hari terakhir.
     */
    private function ambilPerubahanSaldoHarian(
        int $jumlahHari
    ): array {
        $akhirPeriode = now('Asia/Jakarta')
            ->endOfDay();

        $awalPeriode = $akhirPeriode
            ->copy()
            ->subDays($jumlahHari - 1)
            ->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | Transaksi pemasukan dan pengeluaran harian
        |--------------------------------------------------------------------------
        */
        $transaksiHarian = Transaksi::query()
            ->select([
                'dompet_id',
            ])
            ->selectRaw(
                'DATE(tanggal_transaksi) AS tanggal'
            )
            ->selectRaw(
                '
                SUM(
                    CASE
                        WHEN jenis_transaksi = ?
                        THEN nominal
                        ELSE 0
                    END
                ) AS pemasukan
                ',
                [
                    Transaksi::JENIS_PEMASUKAN,
                ]
            )
            ->selectRaw(
                '
                SUM(
                    CASE
                        WHEN jenis_transaksi = ?
                        THEN nominal
                        ELSE 0
                    END
                ) AS pengeluaran
                ',
                [
                    Transaksi::JENIS_PENGELUARAN,
                ]
            )
            ->where(
                'status',
                Transaksi::STATUS_SELESAI
            )
            ->whereBetween(
                'tanggal_transaksi',
                [
                    $awalPeriode,
                    $akhirPeriode,
                ]
            )
            ->groupBy(
                'dompet_id',
                DB::raw('DATE(tanggal_transaksi)')
            )
            ->get()
            ->keyBy(
                fn ($data): string =>
                    "{$data->dompet_id}|{$data->tanggal}"
            );

        /*
        |--------------------------------------------------------------------------
        | Transfer masuk harian
        |--------------------------------------------------------------------------
        */
        $transferMasukHarian = TransferDompet::query()
            ->selectRaw(
                'dompet_tujuan_id AS dompet_id'
            )
            ->selectRaw(
                'DATE(tanggal_transfer) AS tanggal'
            )
            ->selectRaw(
                'COALESCE(SUM(nominal), 0) AS nominal'
            )
            ->where(
                'status',
                TransferDompet::STATUS_SELESAI
            )
            ->whereBetween(
                'tanggal_transfer',
                [
                    $awalPeriode,
                    $akhirPeriode,
                ]
            )
            ->groupBy(
                'dompet_tujuan_id',
                DB::raw('DATE(tanggal_transfer)')
            )
            ->get()
            ->keyBy(
                fn ($data): string =>
                    "{$data->dompet_id}|{$data->tanggal}"
            );

        /*
        |--------------------------------------------------------------------------
        | Transfer keluar dan biaya administrasi harian
        |--------------------------------------------------------------------------
        */
        $transferKeluarHarian = TransferDompet::query()
            ->selectRaw(
                'dompet_asal_id AS dompet_id'
            )
            ->selectRaw(
                'DATE(tanggal_transfer) AS tanggal'
            )
            ->selectRaw(
                '
                COALESCE(
                    SUM(nominal + biaya_admin),
                    0
                ) AS nominal
                '
            )
            ->where(
                'status',
                TransferDompet::STATUS_SELESAI
            )
            ->whereBetween(
                'tanggal_transfer',
                [
                    $awalPeriode,
                    $akhirPeriode,
                ]
            )
            ->groupBy(
                'dompet_asal_id',
                DB::raw('DATE(tanggal_transfer)')
            )
            ->get()
            ->keyBy(
                fn ($data): string =>
                    "{$data->dompet_id}|{$data->tanggal}"
            );

        return [
            'awal_periode' => $awalPeriode,
            'transaksi' => $transaksiHarian,
            'transfer_masuk' => $transferMasukHarian,
            'transfer_keluar' => $transferKeluarHarian,
        ];
    }

    /**
     * Menyusun perubahan saldo harian satu dompet.
     */
    private function susunPerubahanHarianDompet(
        Dompet $dompet,
        array $dataHarian,
        int $jumlahHari
    ): array {
        $perubahanHarian = [];

        for (
            $indeks = 0;
            $indeks < $jumlahHari;
            $indeks++
        ) {
            $tanggal = $dataHarian['awal_periode']
                ->copy()
                ->addDays($indeks)
                ->toDateString();

            $kunci = "{$dompet->id}|{$tanggal}";

            $transaksi = $dataHarian['transaksi']
                ->get($kunci);

            $transferMasuk =
                $dataHarian['transfer_masuk']
                    ->get($kunci);

            $transferKeluar =
                $dataHarian['transfer_keluar']
                    ->get($kunci);

            $pemasukan = (float) (
                $transaksi?->pemasukan
                ?? 0
            );

            $pengeluaran = (float) (
                $transaksi?->pengeluaran
                ?? 0
            );

            $nominalTransferMasuk = (float) (
                $transferMasuk?->nominal
                ?? 0
            );

            $nominalTransferKeluar = (float) (
                $transferKeluar?->nominal
                ?? 0
            );

            /*
            | Jika dompet dibuat pada periode grafik,
            | saldo awal dimasukkan pada tanggal pembuatan.
            */
            $saldoAwalHariIni = 0;

            if (
                $dompet->created_at !== null
                && $dompet->created_at
                    ->timezone('Asia/Jakarta')
                    ->toDateString() === $tanggal
            ) {
                $saldoAwalHariIni =
                    (float) $dompet->saldo_awal;
            }

            $perubahanHarian[] =
                $saldoAwalHariIni
                + $pemasukan
                - $pengeluaran
                + $nominalTransferMasuk
                - $nominalTransferKeluar;
        }

        return $perubahanHarian;
    }

    /**
     * Membentuk mini-chart saldo selama tujuh hari.
     */
    private function buatGrafikSaldoDompet(
        float $saldoSaatIni,
        array $perubahanHarian
    ): array {
        $saldoSebelumPeriode =
            $saldoSaatIni
            - array_sum($perubahanHarian);

        $saldoBerjalan = $saldoSebelumPeriode;
        $grafik = [];

        foreach ($perubahanHarian as $perubahan) {
            $saldoBerjalan += (float) $perubahan;

            $grafik[] = round(
                $saldoBerjalan,
                2
            );
        }

        return $grafik;
    }

    /**
     * Membuat informasi perubahan saldo tujuh hari terakhir.
     */
    private function buatInformasiPerubahan(
        float $perubahan
    ): array {
        if ($perubahan > 0) {
            return [
                'deskripsi' =>
                    'Naik '
                    . $this->formatRupiah($perubahan)
                    . ' dalam 7 hari',
                'ikon' =>
                    'heroicon-m-arrow-trending-up',
            ];
        }

        if ($perubahan < 0) {
            return [
                'deskripsi' =>
                    'Turun '
                    . $this->formatRupiah(
                        abs($perubahan)
                    )
                    . ' dalam 7 hari',
                'ikon' =>
                    'heroicon-m-arrow-trending-down',
            ];
        }

        return [
            'deskripsi' =>
                'Tidak berubah dalam 7 hari',
            'ikon' =>
                'heroicon-m-minus',
        ];
    }

    /**
     * Menentukan warna kartu statistik.
     */
    private function tentukanWarnaStat(
        float $saldoSaatIni,
        string $jenisDompet
    ): string {
        if ($saldoSaatIni < 0) {
            return 'danger';
        }

        if ($saldoSaatIni === 0.0) {
            return 'gray';
        }

        return match ($jenisDompet) {
            'tunai' => 'success',
            'bank' => 'info',
            'dompet_digital' => 'warning',
            default => 'primary',
        };
    }

    /**
     * Mengubah jenis dompet menjadi label yang mudah dibaca.
     */
    private function labelJenisDompet(
        string $jenisDompet
    ): string {
        return match ($jenisDompet) {
            'tunai' => 'Tunai',
            'bank' => 'Rekening Bank',
            'dompet_digital' => 'Dompet Digital',
            'lainnya' => 'Dompet Lainnya',
            default => 'Jenis Tidak Diketahui',
        };
    }

    /**
     * Format mata uang Rupiah.
     */
    private function formatRupiah(
        float $nominal
    ): string {
        $awalan = $nominal < 0
            ? '-Rp '
            : 'Rp ';

        return $awalan . number_format(
            abs($nominal),
            0,
            ',',
            '.'
        );
    }
}