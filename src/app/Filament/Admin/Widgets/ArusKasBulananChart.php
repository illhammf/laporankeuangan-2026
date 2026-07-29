<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Transaksi;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ArusKasBulananChart extends ChartWidget
{
    /**
     * Judul chart.
     */
    protected static ?string $heading = 'Arus Kas Bulanan';

    /**
     * Urutan widget pada dashboard.
     */
    protected static ?int $sort = 3;

    /**
     * Chart ditampilkan setengah layar pada desktop
     * dan penuh pada ukuran layar yang lebih kecil.
     */
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 2,
        'xl' => 1,
    ];

    /**
     * Tinggi maksimum chart.
     */
    protected static ?string $maxHeight = '380px';

    /**
     * Data diperbarui otomatis setiap 60 detik.
     */
    protected static ?string $pollingInterval = '60s';

    /**
     * Widget langsung dimuat ketika dashboard dibuka.
     */
    protected static bool $isLazy = false;

    /**
     * Filter periode bawaan.
     */
    public ?string $filter = '6_bulan';

    /**
     * Deskripsi chart berdasarkan filter aktif.
     */
    public function getDescription(): ?string
    {
        $periode = match ($this->filter) {
            '12_bulan' => '12 bulan terakhir',
            'tahun_ini' => 'tahun berjalan',
            default => '6 bulan terakhir',
        };

        return "Perbandingan pemasukan dan pengeluaran selesai selama {$periode}. "
            . 'Transfer antar-dompet tidak dihitung sebagai pemasukan atau pengeluaran.';
    }

    /**
     * Pilihan filter periode chart.
     */
    protected function getFilters(): ?array
    {
        return [
            '6_bulan' => '6 Bulan Terakhir',
            '12_bulan' => '12 Bulan Terakhir',
            'tahun_ini' => 'Tahun Berjalan',
        ];
    }

    /**
     * Menyiapkan dataset chart.
     */
    protected function getData(): array
    {
        [
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
        ] = $this->tentukanPeriode();

        $rekapTransaksi = Transaksi::query()
            ->selectRaw(
                'YEAR(tanggal_transaksi) AS tahun'
            )
            ->selectRaw(
                'MONTH(tanggal_transaksi) AS bulan'
            )
            ->selectRaw(
                '
                COALESCE(
                    SUM(
                        CASE
                            WHEN jenis_transaksi = ?
                            THEN nominal
                            ELSE 0
                        END
                    ),
                    0
                ) AS total_pemasukan
                ',
                [
                    Transaksi::JENIS_PEMASUKAN,
                ]
            )
            ->selectRaw(
                '
                COALESCE(
                    SUM(
                        CASE
                            WHEN jenis_transaksi = ?
                            THEN nominal
                            ELSE 0
                        END
                    ),
                    0
                ) AS total_pengeluaran
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
                    $tanggalMulai,
                    $tanggalSelesai,
                ]
            )
            ->groupBy(
                DB::raw('YEAR(tanggal_transaksi)'),
                DB::raw('MONTH(tanggal_transaksi)')
            )
            ->orderBy(
                DB::raw('YEAR(tanggal_transaksi)')
            )
            ->orderBy(
                DB::raw('MONTH(tanggal_transaksi)')
            )
            ->get()
            ->keyBy(
                fn ($item): string => sprintf(
                    '%04d-%02d',
                    (int) $item->tahun,
                    (int) $item->bulan
                )
            );

        $labels = [];
        $dataPemasukan = [];
        $dataPengeluaran = [];

        $periodeBerjalan = $tanggalMulai
            ->copy()
            ->startOfMonth();

        $akhirPeriode = $tanggalSelesai
            ->copy()
            ->startOfMonth();

        while ($periodeBerjalan->lte($akhirPeriode)) {
            $kunciPeriode = $periodeBerjalan->format(
                'Y-m'
            );

            $dataPeriode = $rekapTransaksi->get(
                $kunciPeriode
            );

            $labels[] = $this->formatLabelBulan(
                $periodeBerjalan
            );

            $dataPemasukan[] = round(
                (float) (
                    $dataPeriode?->total_pemasukan
                    ?? 0
                ),
                2
            );

            $dataPengeluaran[] = round(
                (float) (
                    $dataPeriode?->total_pengeluaran
                    ?? 0
                ),
                2
            );

            $periodeBerjalan->addMonth();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan',
                    'data' => $dataPemasukan,
                    'backgroundColor' =>
                        'rgba(34, 197, 94, 0.75)',
                    'borderColor' =>
                        'rgb(22, 163, 74)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'maxBarThickness' => 38,
                    'categoryPercentage' => 0.72,
                    'barPercentage' => 0.85,
                ],
                [
                    'label' => 'Pengeluaran',
                    'data' => $dataPengeluaran,
                    'backgroundColor' =>
                        'rgba(239, 68, 68, 0.75)',
                    'borderColor' =>
                        'rgb(220, 38, 38)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'maxBarThickness' => 38,
                    'categoryPercentage' => 0.72,
                    'barPercentage' => 0.85,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Menentukan tipe chart.
     */
    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Konfigurasi Chart.js.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    mode: 'index',
                    intersect: false,
                },

                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',

                        labels: {
                            usePointStyle: true,
                            pointStyle: 'rectRounded',
                            padding: 20,
                        },
                    },

                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const nilai = context.parsed.y ?? 0;

                                const nominal = new Intl.NumberFormat(
                                    'id-ID',
                                    {
                                        style: 'currency',
                                        currency: 'IDR',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    }
                                ).format(nilai);

                                return `${context.dataset.label}: ${nominal}`;
                            },

                            footer: (items) => {
                                if (!items.length) {
                                    return '';
                                }

                                let pemasukan = 0;
                                let pengeluaran = 0;

                                items.forEach((item) => {
                                    const nilai = item.parsed.y ?? 0;

                                    if (
                                        item.dataset.label
                                        === 'Pemasukan'
                                    ) {
                                        pemasukan = nilai;
                                    }

                                    if (
                                        item.dataset.label
                                        === 'Pengeluaran'
                                    ) {
                                        pengeluaran = nilai;
                                    }
                                });

                                const arusKas =
                                    pemasukan - pengeluaran;

                                const nominal = new Intl.NumberFormat(
                                    'id-ID',
                                    {
                                        style: 'currency',
                                        currency: 'IDR',
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    }
                                ).format(arusKas);

                                return `Arus kas bersih: ${nominal}`;
                            },
                        },
                    },
                },

                scales: {
                    x: {
                        grid: {
                            display: false,
                        },

                        ticks: {
                            maxRotation: 0,
                            minRotation: 0,
                        },
                    },

                    y: {
                        beginAtZero: true,

                        grid: {
                            color: 'rgba(148, 163, 184, 0.15)',
                        },

                        ticks: {
                            callback: (value) => {
                                return new Intl.NumberFormat(
                                    'id-ID',
                                    {
                                        notation: 'compact',
                                        compactDisplay: 'short',
                                        maximumFractionDigits: 1,
                                    }
                                ).format(value);
                            },
                        },
                    },
                },
            }
        JS);
    }

    /**
     * Menentukan tanggal awal dan akhir berdasarkan filter.
     */
    private function tentukanPeriode(): array
    {
        $sekarang = now('Asia/Jakarta');

        return match ($this->filter) {
            '12_bulan' => [
                'tanggal_mulai' => $sekarang
                    ->copy()
                    ->subMonthsNoOverflow(11)
                    ->startOfMonth(),

                'tanggal_selesai' => $sekarang
                    ->copy()
                    ->endOfMonth(),
            ],

            'tahun_ini' => [
                'tanggal_mulai' => $sekarang
                    ->copy()
                    ->startOfYear(),

                'tanggal_selesai' => $sekarang
                    ->copy()
                    ->endOfMonth(),
            ],

            default => [
                'tanggal_mulai' => $sekarang
                    ->copy()
                    ->subMonthsNoOverflow(5)
                    ->startOfMonth(),

                'tanggal_selesai' => $sekarang
                    ->copy()
                    ->endOfMonth(),
            ],
        };
    }

    /**
     * Membuat label bulan dalam Bahasa Indonesia.
     *
     * Contoh:
     * Jul 2026
     */
    private function formatLabelBulan(
        Carbon $tanggal
    ): string {
        $namaBulan = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        return $namaBulan[$tanggal->month]
            . ' '
            . $tanggal->year;
    }
}