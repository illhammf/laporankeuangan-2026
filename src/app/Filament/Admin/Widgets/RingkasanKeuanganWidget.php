<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Dompet;
use App\Models\Transaksi;
use App\Models\TransferDompet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RingkasanKeuanganWidget extends BaseWidget
{
    /**
     * Urutan widget pada dashboard.
     */
    protected static ?int $sort = 1;

    /**
     * Widget menggunakan lebar penuh dashboard.
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Memperbarui data otomatis setiap 30 detik.
     */
    protected static ?string $pollingInterval = '30s';

    /**
     * Langsung memuat widget saat dashboard dibuka.
     */
    protected static bool $isLazy = false;

    /**
     * Judul widget.
     */
    protected ?string $heading = 'Ringkasan Keuangan';

    /**
     * Deskripsi dinamis widget.
     */
    protected function getDescription(): ?string
    {
        return 'Ringkasan saldo dan aktivitas keuangan seluruh pengguna hingga '
            . now('Asia/Jakarta')->format('d/m/Y H:i')
            . ' WIB.';
    }

    /**
     * Statistik utama dashboard.
     */
    protected function getStats(): array
    {
        $sekarang = now('Asia/Jakarta');

        /*
        |--------------------------------------------------------------------------
        | Periode bulan berjalan
        |--------------------------------------------------------------------------
        */
        $awalBulanIni = $sekarang
            ->copy()
            ->startOfMonth();

        $akhirBulanIni = $sekarang->copy();

        /*
        |--------------------------------------------------------------------------
        | Periode pembanding bulan sebelumnya
        |--------------------------------------------------------------------------
        |
        | Contoh:
        | Jika hari ini 28 Juli, maka periode pembanding adalah
        | 1 Juni sampai 28 Juni pada jam yang sama.
        |
        */
        $tanggalBulanLalu = $sekarang
            ->copy()
            ->subMonthNoOverflow();

        $awalBulanLalu = $tanggalBulanLalu
            ->copy()
            ->startOfMonth();

        $akhirBulanLalu = $tanggalBulanLalu
            ->copy()
            ->setTime(
                $sekarang->hour,
                $sekarang->minute,
                $sekarang->second
            );

        /*
        |--------------------------------------------------------------------------
        | Saldo seluruh dompet
        |--------------------------------------------------------------------------
        */
        $ringkasanDompet = $this->ambilRingkasanSaldoDompet();

        $totalSaldo = (float) $ringkasanDompet->sum(
            'saldo_saat_ini'
        );

        $jumlahDompet = $ringkasanDompet->count();

        $jumlahDompetAktif = $ringkasanDompet
            ->where('aktif', true)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Pemasukan bulan berjalan dan pembanding
        |--------------------------------------------------------------------------
        */
        $pemasukanBulanIni = $this->hitungTotalTransaksi(
            jenisTransaksi: Transaksi::JENIS_PEMASUKAN,
            tanggalMulai: $awalBulanIni,
            tanggalSelesai: $akhirBulanIni
        );

        $pemasukanBulanLalu = $this->hitungTotalTransaksi(
            jenisTransaksi: Transaksi::JENIS_PEMASUKAN,
            tanggalMulai: $awalBulanLalu,
            tanggalSelesai: $akhirBulanLalu
        );

        /*
        |--------------------------------------------------------------------------
        | Pengeluaran bulan berjalan dan pembanding
        |--------------------------------------------------------------------------
        */
        $pengeluaranBulanIni = $this->hitungTotalTransaksi(
            jenisTransaksi: Transaksi::JENIS_PENGELUARAN,
            tanggalMulai: $awalBulanIni,
            tanggalSelesai: $akhirBulanIni
        );

        $pengeluaranBulanLalu = $this->hitungTotalTransaksi(
            jenisTransaksi: Transaksi::JENIS_PENGELUARAN,
            tanggalMulai: $awalBulanLalu,
            tanggalSelesai: $akhirBulanLalu
        );

        /*
        |--------------------------------------------------------------------------
        | Arus kas bersih
        |--------------------------------------------------------------------------
        */
        $arusKasBersih = $pemasukanBulanIni
            - $pengeluaranBulanIni;

        /*
        |--------------------------------------------------------------------------
        | Data mini-chart tujuh hari terakhir
        |--------------------------------------------------------------------------
        */
        $dataHarian = $this->ambilDataHarian(
            jumlahHari: 7
        );

        $grafikSaldo = $this->buatGrafikSaldo(
            totalSaldo: $totalSaldo,
            perubahanSaldoHarian:
                $dataHarian['perubahan_saldo']
        );

        $trenPemasukan = $this->buatPerbandingan(
            nilaiSekarang: $pemasukanBulanIni,
            nilaiSebelumnya: $pemasukanBulanLalu
        );

        $trenPengeluaran = $this->buatPerbandingan(
            nilaiSekarang: $pengeluaranBulanIni,
            nilaiSebelumnya: $pengeluaranBulanLalu
        );

        $kondisiArusKas = $this->tentukanKondisiArusKas(
            arusKasBersih: $arusKasBersih
        );

        return [
            Stat::make(
                'Total Saldo',
                $this->formatRupiah($totalSaldo)
            )
                ->description(
                    "{$jumlahDompetAktif} dompet aktif dari "
                    . "{$jumlahDompet} dompet"
                )
                ->descriptionIcon('heroicon-m-wallet')
                ->chart($grafikSaldo)
                ->color(
                    $totalSaldo < 0
                        ? 'danger'
                        : 'primary'
                ),

            Stat::make(
                'Pemasukan Bulan Ini',
                $this->formatRupiah($pemasukanBulanIni)
            )
                ->description(
                    $trenPemasukan['deskripsi']
                )
                ->descriptionIcon(
                    $trenPemasukan['ikon']
                )
                ->chart($dataHarian['pemasukan'])
                ->color('success'),

            Stat::make(
                'Pengeluaran Bulan Ini',
                $this->formatRupiah($pengeluaranBulanIni)
            )
                ->description(
                    $trenPengeluaran['deskripsi']
                )
                ->descriptionIcon(
                    $trenPengeluaran['ikon']
                )
                ->chart($dataHarian['pengeluaran'])
                ->color('danger'),

            Stat::make(
                'Arus Kas Bersih',
                $this->formatRupiah($arusKasBersih)
            )
                ->description(
                    $kondisiArusKas['deskripsi']
                )
                ->descriptionIcon(
                    $kondisiArusKas['ikon']
                )
                ->chart($dataHarian['arus_kas_bersih'])
                ->color(
                    $kondisiArusKas['warna']
                ),
        ];
    }

    /**
     * Mengambil saldo aktual seluruh dompet.
     *
     * Saldo aktual:
     * saldo awal
     * + pemasukan selesai
     * - pengeluaran selesai
     * + transfer masuk selesai
     * - transfer keluar selesai
     * - biaya administrasi transfer.
     */
    private function ambilRingkasanSaldoDompet(): Collection
    {
        return Dompet::query()
            ->withSum([
                'transaksi as total_pemasukan_selesai' =>
                    fn ($query) => $query
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
                    fn ($query) => $query
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
                    fn ($query) => $query
                        ->where(
                            'status',
                            TransferDompet::STATUS_SELESAI
                        ),
            ], 'nominal')
            ->withSum([
                'transferKeluar as total_transfer_keluar_selesai' =>
                    fn ($query) => $query
                        ->where(
                            'status',
                            TransferDompet::STATUS_SELESAI
                        ),
            ], 'nominal')
            ->withSum([
                'transferKeluar as total_biaya_admin_transfer' =>
                    fn ($query) => $query
                        ->where(
                            'status',
                            TransferDompet::STATUS_SELESAI
                        ),
            ], 'biaya_admin')
            ->orderBy('pengguna_id')
            ->orderBy('urutan')
            ->orderBy('nama_dompet')
            ->get()
            ->map(
                function (Dompet $dompet): array {
                    $saldoSaatIni =
                        (float) $dompet->saldo_awal
                        + (float) (
                            $dompet
                                ->total_pemasukan_selesai
                            ?? 0
                        )
                        - (float) (
                            $dompet
                                ->total_pengeluaran_selesai
                            ?? 0
                        )
                        + (float) (
                            $dompet
                                ->total_transfer_masuk_selesai
                            ?? 0
                        )
                        - (float) (
                            $dompet
                                ->total_transfer_keluar_selesai
                            ?? 0
                        )
                        - (float) (
                            $dompet
                                ->total_biaya_admin_transfer
                            ?? 0
                        );

                    return [
                        'id' => $dompet->id,
                        'pengguna_id' => $dompet->pengguna_id,
                        'nama_dompet' =>
                            $dompet->nama_dompet,
                        'aktif' => (bool) $dompet->aktif,
                        'saldo_saat_ini' => $saldoSaatIni,
                    ];
                }
            );
    }

    /**
     * Menghitung total transaksi dalam periode tertentu.
     */
    private function hitungTotalTransaksi(
        string $jenisTransaksi,
        mixed $tanggalMulai,
        mixed $tanggalSelesai
    ): float {
        return (float) Transaksi::query()
            ->where(
                'jenis_transaksi',
                $jenisTransaksi
            )
            ->where(
                'status',
                Transaksi::STATUS_SELESAI
            )
            ->whereBetween(
                'tanggal_transaksi',
                [
                    $tanggalMulai,
                    $tanggalSelesai,
                ]
            )
            ->sum('nominal');
    }

    /**
     * Mengambil aktivitas harian selama beberapa hari terakhir.
     */
    private function ambilDataHarian(
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
        | Pemasukan dan pengeluaran per hari
        |--------------------------------------------------------------------------
        */
        $transaksiHarian = Transaksi::query()
            ->selectRaw(
                'DATE(tanggal_transaksi) AS tanggal'
            )
            ->selectRaw(
                "
                SUM(
                    CASE
                        WHEN jenis_transaksi = ?
                        THEN nominal
                        ELSE 0
                    END
                ) AS pemasukan
                ",
                [
                    Transaksi::JENIS_PEMASUKAN,
                ]
            )
            ->selectRaw(
                "
                SUM(
                    CASE
                        WHEN jenis_transaksi = ?
                        THEN nominal
                        ELSE 0
                    END
                ) AS pengeluaran
                ",
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
                DB::raw('DATE(tanggal_transaksi)')
            )
            ->get()
            ->keyBy('tanggal');

        /*
        |--------------------------------------------------------------------------
        | Biaya administrasi transfer per hari
        |--------------------------------------------------------------------------
        */
        $biayaAdminHarian = TransferDompet::query()
            ->selectRaw(
                'DATE(tanggal_transfer) AS tanggal'
            )
            ->selectRaw(
                'COALESCE(SUM(biaya_admin), 0) AS biaya_admin'
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
                DB::raw('DATE(tanggal_transfer)')
            )
            ->get()
            ->keyBy('tanggal');

        /*
        |--------------------------------------------------------------------------
        | Saldo awal dompet baru per hari
        |--------------------------------------------------------------------------
        */
        $saldoAwalHarian = Dompet::query()
            ->selectRaw(
                'DATE(created_at) AS tanggal'
            )
            ->selectRaw(
                'COALESCE(SUM(saldo_awal), 0) AS saldo_awal'
            )
            ->whereBetween(
                'created_at',
                [
                    $awalPeriode,
                    $akhirPeriode,
                ]
            )
            ->groupBy(
                DB::raw('DATE(created_at)')
            )
            ->get()
            ->keyBy('tanggal');

        $pemasukan = [];
        $pengeluaran = [];
        $arusKasBersih = [];
        $perubahanSaldo = [];

        for (
            $indeks = 0;
            $indeks < $jumlahHari;
            $indeks++
        ) {
            $tanggal = $awalPeriode
                ->copy()
                ->addDays($indeks)
                ->toDateString();

            $dataTransaksi =
                $transaksiHarian->get($tanggal);

            $dataBiayaAdmin =
                $biayaAdminHarian->get($tanggal);

            $dataSaldoAwal =
                $saldoAwalHarian->get($tanggal);

            $nilaiPemasukan = (float) (
                $dataTransaksi?->pemasukan
                ?? 0
            );

            $nilaiPengeluaran = (float) (
                $dataTransaksi?->pengeluaran
                ?? 0
            );

            $nilaiBiayaAdmin = (float) (
                $dataBiayaAdmin?->biaya_admin
                ?? 0
            );

            $nilaiSaldoAwal = (float) (
                $dataSaldoAwal?->saldo_awal
                ?? 0
            );

            $nilaiArusKasBersih =
                $nilaiPemasukan
                - $nilaiPengeluaran;

            $nilaiPerubahanSaldo =
                $nilaiSaldoAwal
                + $nilaiPemasukan
                - $nilaiPengeluaran
                - $nilaiBiayaAdmin;

            $pemasukan[] = $nilaiPemasukan;
            $pengeluaran[] = $nilaiPengeluaran;
            $arusKasBersih[] =
                $nilaiArusKasBersih;
            $perubahanSaldo[] =
                $nilaiPerubahanSaldo;
        }

        return [
            'pemasukan' => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'arus_kas_bersih' => $arusKasBersih,
            'perubahan_saldo' => $perubahanSaldo,
        ];
    }

    /**
     * Membuat grafik tren saldo tujuh hari terakhir.
     */
    private function buatGrafikSaldo(
        float $totalSaldo,
        array $perubahanSaldoHarian
    ): array {
        $saldoSebelumPeriode =
            $totalSaldo
            - array_sum($perubahanSaldoHarian);

        $saldoBerjalan = $saldoSebelumPeriode;
        $grafikSaldo = [];

        foreach (
            $perubahanSaldoHarian
            as $perubahanSaldo
        ) {
            $saldoBerjalan += (float) $perubahanSaldo;

            $grafikSaldo[] = round(
                $saldoBerjalan,
                2
            );
        }

        return $grafikSaldo;
    }

    /**
     * Membuat deskripsi perbandingan dengan periode sama
     * pada bulan sebelumnya.
     */
    private function buatPerbandingan(
        float $nilaiSekarang,
        float $nilaiSebelumnya
    ): array {
        if (
            $nilaiSekarang === 0.0
            && $nilaiSebelumnya === 0.0
        ) {
            return [
                'deskripsi' =>
                    'Belum ada transaksi pada kedua periode',
                'ikon' =>
                    'heroicon-m-minus-small',
            ];
        }

        if ($nilaiSebelumnya === 0.0) {
            return [
                'deskripsi' =>
                    'Mulai tercatat pada bulan ini',
                'ikon' =>
                    'heroicon-m-arrow-trending-up',
            ];
        }

        $selisih = $nilaiSekarang
            - $nilaiSebelumnya;

        $persentase = abs(
            ($selisih / $nilaiSebelumnya)
            * 100
        );

        if ($selisih > 0) {
            return [
                'deskripsi' =>
                    number_format(
                        $persentase,
                        1,
                        ',',
                        '.'
                    )
                    . '% lebih tinggi dari periode sama bulan lalu',
                'ikon' =>
                    'heroicon-m-arrow-trending-up',
            ];
        }

        if ($selisih < 0) {
            return [
                'deskripsi' =>
                    number_format(
                        $persentase,
                        1,
                        ',',
                        '.'
                    )
                    . '% lebih rendah dari periode sama bulan lalu',
                'ikon' =>
                    'heroicon-m-arrow-trending-down',
            ];
        }

        return [
            'deskripsi' =>
                'Sama dengan periode bulan lalu',
            'ikon' =>
                'heroicon-m-arrows-right-left',
        ];
    }

    /**
     * Menentukan kondisi arus kas bersih.
     */
    private function tentukanKondisiArusKas(
        float $arusKasBersih
    ): array {
        if ($arusKasBersih > 0) {
            return [
                'deskripsi' =>
                    'Surplus pada bulan berjalan',
                'ikon' =>
                    'heroicon-m-arrow-trending-up',
                'warna' => 'success',
            ];
        }

        if ($arusKasBersih < 0) {
            return [
                'deskripsi' =>
                    'Defisit pada bulan berjalan',
                'ikon' =>
                    'heroicon-m-arrow-trending-down',
                'warna' => 'danger',
            ];
        }

        return [
            'deskripsi' =>
                'Pemasukan dan pengeluaran seimbang',
            'ikon' =>
                'heroicon-m-arrows-right-left',
            'warna' => 'gray',
        ];
    }

    /**
     * Format mata uang Rupiah Indonesia.
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