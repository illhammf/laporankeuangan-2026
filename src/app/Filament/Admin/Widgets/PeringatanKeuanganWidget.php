<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AnggaranBulanan;
use App\Models\Dompet;
use App\Models\Transaksi;
use App\Models\TransferDompet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PeringatanKeuanganWidget extends BaseWidget
{
    /**
     * Urutan widget pada dashboard.
     */
    protected static ?int $sort = 6;

    /**
     * Widget menggunakan lebar penuh.
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Data diperbarui otomatis setiap 60 detik.
     */
    protected static ?string $pollingInterval = '60s';

    /**
     * Widget langsung dimuat saat dashboard dibuka.
     */
    protected static bool $isLazy = false;

    /**
     * Judul widget.
     */
    protected ?string $heading = 'Peringatan Keuangan';

    /**
     * Deskripsi widget.
     */
    protected function getDescription(): ?string
    {
        return 'Pemantauan kondisi yang memerlukan perhatian hingga '
            . now('Asia/Jakarta')->format('d/m/Y H:i')
            . ' WIB.';
    }

    /**
     * Statistik peringatan keuangan.
     */
    protected function getStats(): array
    {
        $sekarang = now('Asia/Jakarta');

        /*
        |--------------------------------------------------------------------------
        | Pemantauan anggaran bulan berjalan
        |--------------------------------------------------------------------------
        */
        $daftarAnggaran = $this->ambilAnggaranBulanBerjalan(
            bulan: $sekarang->month,
            tahun: $sekarang->year
        );

        $anggaranMendekatiBatas = $daftarAnggaran
            ->filter(
                function (
                    AnggaranBulanan $anggaran
                ): bool {
                    $persentase = $this->hitungPersentase(
                        totalTerpakai: (float) (
                            $anggaran->total_terpakai_widget
                            ?? 0
                        ),
                        nominalAnggaran: (float) (
                            $anggaran->nominal_anggaran
                        )
                    );

                    return $persentase
                        >= (float) $anggaran
                            ->batas_peringatan
                        && $persentase < 100;
                }
            );

        $anggaranHabisAtauTerlampaui = $daftarAnggaran
            ->filter(
                function (
                    AnggaranBulanan $anggaran
                ): bool {
                    $persentase = $this->hitungPersentase(
                        totalTerpakai: (float) (
                            $anggaran->total_terpakai_widget
                            ?? 0
                        ),
                        nominalAnggaran: (float) (
                            $anggaran->nominal_anggaran
                        )
                    );

                    return $persentase >= 100;
                }
            );

        $totalSisaAnggaranPeringatan =
            $anggaranMendekatiBatas->sum(
                function (
                    AnggaranBulanan $anggaran
                ): float {
                    return max(
                        0,
                        (float) $anggaran
                            ->nominal_anggaran
                        - (float) (
                            $anggaran
                                ->total_terpakai_widget
                            ?? 0
                        )
                    );
                }
            );

        $totalKelebihanAnggaran =
            $anggaranHabisAtauTerlampaui->sum(
                function (
                    AnggaranBulanan $anggaran
                ): float {
                    return max(
                        0,
                        (float) (
                            $anggaran
                                ->total_terpakai_widget
                            ?? 0
                        )
                        - (float) $anggaran
                            ->nominal_anggaran
                    );
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Transaksi tertunda
        |--------------------------------------------------------------------------
        */
        $queryTransaksiTertunda = Transaksi::query()
            ->where(
                'status',
                Transaksi::STATUS_TERTUNDA
            );

        $jumlahTransaksiTertunda =
            (clone $queryTransaksiTertunda)->count();

        $nominalTransaksiTertunda = (float) (
            (clone $queryTransaksiTertunda)
                ->sum('nominal')
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer tertunda
        |--------------------------------------------------------------------------
        */
        $queryTransferTertunda =
            TransferDompet::query()
                ->where(
                    'status',
                    TransferDompet::STATUS_TERTUNDA
                );

        $jumlahTransferTertunda =
            (clone $queryTransferTertunda)->count();

        $nominalTransferTertunda = (float) (
            (clone $queryTransferTertunda)
                ->sum('nominal')
        );

        $biayaAdminTransferTertunda = (float) (
            (clone $queryTransferTertunda)
                ->sum('biaya_admin')
        );

        $totalTransferTertunda =
            $nominalTransferTertunda
            + $biayaAdminTransferTertunda;

        /*
        |--------------------------------------------------------------------------
        | Dompet bersaldo negatif
        |--------------------------------------------------------------------------
        */
        $dompetBersaldoNegatif =
            $this->ambilDompetBersaldoNegatif();

        $jumlahDompetNegatif =
            $dompetBersaldoNegatif->count();

        $totalDefisitDompet =
            abs(
                (float) $dompetBersaldoNegatif->sum(
                    'saldo_saat_ini'
                )
            );

        return [
            Stat::make(
                'Anggaran Mendekati Batas',
                $this->formatJumlah(
                    $anggaranMendekatiBatas->count(),
                    'anggaran'
                )
            )
                ->description(
                    $anggaranMendekatiBatas->isNotEmpty()
                        ? 'Sisa gabungan '
                            . $this->formatRupiah(
                                (float) $totalSisaAnggaranPeringatan
                            )
                        : 'Seluruh anggaran masih dalam batas aman'
                )
                ->descriptionIcon(
                    $anggaranMendekatiBatas->isNotEmpty()
                        ? 'heroicon-m-exclamation-triangle'
                        : 'heroicon-m-check-circle'
                )
                ->color(
                    $anggaranMendekatiBatas->isNotEmpty()
                        ? 'warning'
                        : 'success'
                ),

            Stat::make(
                'Anggaran Habis atau Terlampaui',
                $this->formatJumlah(
                    $anggaranHabisAtauTerlampaui->count(),
                    'anggaran'
                )
            )
                ->description(
                    $anggaranHabisAtauTerlampaui->isNotEmpty()
                        ? (
                            $totalKelebihanAnggaran > 0
                                ? 'Kelebihan pengeluaran '
                                    . $this->formatRupiah(
                                        (float) $totalKelebihanAnggaran
                                    )
                                : 'Terdapat anggaran yang telah habis'
                        )
                        : 'Belum ada anggaran yang habis'
                )
                ->descriptionIcon(
                    $anggaranHabisAtauTerlampaui->isNotEmpty()
                        ? 'heroicon-m-fire'
                        : 'heroicon-m-check-circle'
                )
                ->color(
                    $anggaranHabisAtauTerlampaui->isNotEmpty()
                        ? 'danger'
                        : 'success'
                ),

            Stat::make(
                'Transaksi Tertunda',
                $this->formatJumlah(
                    $jumlahTransaksiTertunda,
                    'transaksi'
                )
            )
                ->description(
                    $jumlahTransaksiTertunda > 0
                        ? 'Nominal menunggu '
                            . $this->formatRupiah(
                                $nominalTransaksiTertunda
                            )
                        : 'Tidak ada transaksi tertunda'
                )
                ->descriptionIcon(
                    $jumlahTransaksiTertunda > 0
                        ? 'heroicon-m-clock'
                        : 'heroicon-m-check-circle'
                )
                ->color(
                    $jumlahTransaksiTertunda > 0
                        ? 'warning'
                        : 'success'
                ),

            Stat::make(
                'Transfer Tertunda',
                $this->formatJumlah(
                    $jumlahTransferTertunda,
                    'transfer'
                )
            )
                ->description(
                    $jumlahTransferTertunda > 0
                        ? 'Total dana dan biaya '
                            . $this->formatRupiah(
                                $totalTransferTertunda
                            )
                        : 'Tidak ada transfer tertunda'
                )
                ->descriptionIcon(
                    $jumlahTransferTertunda > 0
                        ? 'heroicon-m-arrow-path'
                        : 'heroicon-m-check-circle'
                )
                ->color(
                    $jumlahTransferTertunda > 0
                        ? 'warning'
                        : 'success'
                ),

            Stat::make(
                'Dompet Saldo Negatif',
                $this->formatJumlah(
                    $jumlahDompetNegatif,
                    'dompet'
                )
            )
                ->description(
                    $jumlahDompetNegatif > 0
                        ? 'Total defisit '
                            . $this->formatRupiah(
                                $totalDefisitDompet
                            )
                        : 'Seluruh dompet memiliki saldo normal'
                )
                ->descriptionIcon(
                    $jumlahDompetNegatif > 0
                        ? 'heroicon-m-arrow-trending-down'
                        : 'heroicon-m-check-circle'
                )
                ->color(
                    $jumlahDompetNegatif > 0
                        ? 'danger'
                        : 'success'
                ),
        ];
    }

    /**
     * Mengambil anggaran aktif pada bulan berjalan
     * beserta realisasi pengeluarannya.
     */
    private function ambilAnggaranBulanBerjalan(
        int $bulan,
        int $tahun
    ): Collection {
        /*
        |--------------------------------------------------------------------------
        | Subquery realisasi anggaran
        |--------------------------------------------------------------------------
        |
        | Pengeluaran dihitung dari:
        | 1. Kategori yang sama dengan kategori anggaran.
        | 2. Subkategori langsung dari kategori anggaran.
        |
        */
        $subqueryRealisasi = DB::table('transaksi')
            ->selectRaw(
                'COALESCE(SUM(transaksi.nominal), 0)'
            )
            ->whereColumn(
                'transaksi.pengguna_id',
                'anggaran_bulanan.pengguna_id'
            )
            ->where(
                'transaksi.jenis_transaksi',
                Transaksi::JENIS_PENGELUARAN
            )
            ->where(
                'transaksi.status',
                Transaksi::STATUS_SELESAI
            )
            ->whereNull('transaksi.deleted_at')
            ->whereRaw(
                'MONTH(transaksi.tanggal_transaksi)
                = anggaran_bulanan.bulan'
            )
            ->whereRaw(
                'YEAR(transaksi.tanggal_transaksi)
                = anggaran_bulanan.tahun'
            )
            ->where(
                function ($query): void {
                    $query
                        ->whereColumn(
                            'transaksi.kategori_id',
                            'anggaran_bulanan.kategori_id'
                        )
                        ->orWhereExists(
                            function ($subquery): void {
                                $subquery
                                    ->selectRaw('1')
                                    ->from(
                                        'kategori as kategori_anak'
                                    )
                                    ->whereColumn(
                                        'kategori_anak.id',
                                        'transaksi.kategori_id'
                                    )
                                    ->whereColumn(
                                        'kategori_anak.kategori_induk_id',
                                        'anggaran_bulanan.kategori_id'
                                    )
                                    ->whereNull(
                                        'kategori_anak.deleted_at'
                                    );
                            }
                        );
                }
            );

        return AnggaranBulanan::query()
            ->select('anggaran_bulanan.*')
            ->selectSub(
                $subqueryRealisasi,
                'total_terpakai_widget'
            )
            ->where('bulan', $bulan)
            ->where('tahun', $tahun)
            ->where(
                'status',
                AnggaranBulanan::STATUS_AKTIF
            )
            ->get();
    }

    /**
     * Mengambil seluruh dompet aktif dengan saldo negatif.
     */
    private function ambilDompetBersaldoNegatif(): Collection
    {
        return Dompet::query()
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
                        'nama_dompet' =>
                            $dompet->nama_dompet,
                        'saldo_saat_ini' => $saldoSaatIni,
                    ];
                }
            )
            ->filter(
                fn (array $dompet): bool =>
                    $dompet['saldo_saat_ini'] < 0
            )
            ->values();
    }

    /**
     * Menghitung persentase penggunaan anggaran.
     */
    private function hitungPersentase(
        float $totalTerpakai,
        float $nominalAnggaran
    ): float {
        if ($nominalAnggaran <= 0) {
            return 0;
        }

        return round(
            (
                $totalTerpakai
                / $nominalAnggaran
            ) * 100,
            2
        );
    }

    /**
     * Menampilkan jumlah dengan label.
     */
    private function formatJumlah(
        int $jumlah,
        string $label
    ): string {
        return number_format(
            $jumlah,
            0,
            ',',
            '.'
        ) . " {$label}";
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