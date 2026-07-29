<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AnggaranBulanan;
use App\Models\Transaksi;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PemantauanAnggaranWidget extends ChartWidget
{
    /**
     * Judul widget.
     */
    protected static ?string $heading =
        'Pemantauan Anggaran';

    /**
     * Urutan widget pada dashboard.
     */
    protected static ?int $sort = 5;

    /**
     * Tampil setengah layar pada desktop.
     */
    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 2,
        'xl' => 1,
    ];

    /**
     * Tinggi maksimum chart.
     */
    protected static ?string $maxHeight = '430px';

    /**
     * Memperbarui data setiap 60 detik.
     */
    protected static ?string $pollingInterval = '60s';

    /**
     * Memuat widget langsung saat dashboard dibuka.
     */
    protected static bool $isLazy = false;

    /**
     * Filter periode bawaan.
     */
    public ?string $filter = 'bulan_ini';

    /**
     * Deskripsi periode yang sedang ditampilkan.
     */
    public function getDescription(): ?string
    {
        $periode = $this->tentukanPeriode();

        $namaBulan = AnggaranBulanan::daftarBulan()[
            $periode['bulan']
        ] ?? '-';

        return "Realisasi anggaran {$namaBulan} "
            . "{$periode['tahun']}. "
            . 'Menampilkan maksimal delapan anggaran '
            . 'dengan penggunaan tertinggi.';
    }

    /**
     * Filter periode pemantauan.
     */
    protected function getFilters(): ?array
    {
        return [
            'bulan_ini' => 'Bulan Ini',
            'bulan_lalu' => 'Bulan Lalu',
            'bulan_depan' => 'Bulan Depan',
        ];
    }

    /**
     * Menyiapkan data chart.
     */
    protected function getData(): array
    {
        $periode = $this->tentukanPeriode();

        /*
        |--------------------------------------------------------------------------
        | Subquery realisasi anggaran
        |--------------------------------------------------------------------------
        |
        | Pengeluaran dihitung dari:
        | 1. Transaksi yang langsung menggunakan kategori anggaran.
        | 2. Transaksi yang menggunakan subkategori dari kategori anggaran.
        |
        | Contoh:
        | Anggaran dibuat untuk "Makanan dan Minuman".
        | Transaksi "Makan Harian" dan "Jajan" tetap ikut dihitung.
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

        $daftarAnggaran = AnggaranBulanan::query()
            ->select('anggaran_bulanan.*')
            ->selectSub(
                $subqueryRealisasi,
                'total_terpakai_widget'
            )
            ->with([
                'pengguna',

                'kategori' => fn (
                    Builder $query
                ): Builder => $query
                    ->withTrashed()
                    ->with([
                        'kategoriInduk' => fn (
                            Builder $query
                        ): Builder => $query
                            ->withTrashed(),
                    ]),
            ])
            ->where(
                'bulan',
                $periode['bulan']
            )
            ->where(
                'tahun',
                $periode['tahun']
            )
            ->where(
                'status',
                '!=',
                AnggaranBulanan::STATUS_DIBATALKAN
            )
            ->get()
            ->sortByDesc(
                fn (
                    AnggaranBulanan $anggaran
                ): float => $this->hitungPersentase(
                    totalTerpakai: (float) (
                        $anggaran->total_terpakai_widget
                        ?? 0
                    ),
                    nominalAnggaran: (float) (
                        $anggaran->nominal_anggaran
                    )
                )
            )
            ->take(8)
            ->values();

        if ($daftarAnggaran->isEmpty()) {
            return $this->dataKosong();
        }

        $labels = [];
        $persentaseTerpakai = [];
        $persentaseSisa = [];
        $warnaTerpakai = [];

        $nominalTerpakai = [];
        $nominalAnggaran = [];
        $nominalSisa = [];
        $batasPeringatan = [];
        $kondisiAnggaran = [];

        foreach ($daftarAnggaran as $anggaran) {
            $totalTerpakai = (float) (
                $anggaran->total_terpakai_widget
                ?? 0
            );

            $totalAnggaran = (float) (
                $anggaran->nominal_anggaran
            );

            $sisaAnggaran = $totalAnggaran
                - $totalTerpakai;

            $persentase = $this->hitungPersentase(
                totalTerpakai: $totalTerpakai,
                nominalAnggaran: $totalAnggaran
            );

            $batas = (float) (
                $anggaran->batas_peringatan
            );

            $kondisi = $this->tentukanKondisi(
                persentase: $persentase,
                batasPeringatan: $batas
            );

            $namaKategori = $anggaran
                ->kategori
                ?->nama_kategori
                ?? 'Kategori tidak tersedia';

            $namaPemilik = $anggaran
                ->pengguna
                ?->name
                ?? 'Pemilik tidak tersedia';

            $labels[] = $namaKategori
                . ' — '
                . $namaPemilik;

            /*
            | Persentase terpakai dapat melebihi 100%.
            | Nilai tersebut tetap ditampilkan agar kondisi
            | overbudget terlihat dengan jelas.
            */
            $persentaseTerpakai[] = round(
                $persentase,
                2
            );

            $persentaseSisa[] = round(
                max(0, 100 - $persentase),
                2
            );

            $warnaTerpakai[] = $this->tentukanWarna(
                persentase: $persentase,
                batasPeringatan: $batas
            );

            $nominalTerpakai[] = round(
                $totalTerpakai,
                2
            );

            $nominalAnggaran[] = round(
                $totalAnggaran,
                2
            );

            $nominalSisa[] = round(
                $sisaAnggaran,
                2
            );

            $batasPeringatan[] = round(
                $batas,
                2
            );

            $kondisiAnggaran[] = $kondisi;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Terpakai',
                    'data' => $persentaseTerpakai,
                    'backgroundColor' => $warnaTerpakai,
                    'borderWidth' => 0,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'barThickness' => 20,

                    /*
                    | Metadata tambahan untuk tooltip.
                    */
                    'nominalTerpakai' => $nominalTerpakai,
                    'nominalAnggaran' => $nominalAnggaran,
                    'batasPeringatan' => $batasPeringatan,
                    'kondisiAnggaran' => $kondisiAnggaran,
                ],
                [
                    'label' => 'Sisa',
                    'data' => $persentaseSisa,
                    'backgroundColor' =>
                        'rgba(148, 163, 184, 0.22)',
                    'borderWidth' => 0,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'barThickness' => 20,

                    /*
                    | Metadata tambahan untuk tooltip.
                    */
                    'nominalSisa' => $nominalSisa,
                    'nominalAnggaran' => $nominalAnggaran,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Menggunakan horizontal bar chart.
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
                indexAxis: 'y',

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
                            padding: 18,
                        },
                    },

                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const indeks =
                                    context.dataIndex;

                                const nilai =
                                    Number(context.raw ?? 0);

                                const formatRupiah = (
                                    nominal
                                ) => {
                                    return new Intl.NumberFormat(
                                        'id-ID',
                                        {
                                            style: 'currency',
                                            currency: 'IDR',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        }
                                    ).format(nominal);
                                };

                                if (
                                    context.dataset.label
                                    === 'Terpakai'
                                ) {
                                    const terpakai = Number(
                                        context.dataset
                                            .nominalTerpakai
                                            ?.[indeks]
                                        ?? 0
                                    );

                                    const anggaran = Number(
                                        context.dataset
                                            .nominalAnggaran
                                            ?.[indeks]
                                        ?? 0
                                    );

                                    return [
                                        `Terpakai: ${formatRupiah(terpakai)}`,
                                        `Anggaran: ${formatRupiah(anggaran)}`,
                                        `Penggunaan: ${nilai.toFixed(1)}%`,
                                    ];
                                }

                                const sisa = Number(
                                    context.dataset
                                        .nominalSisa
                                        ?.[indeks]
                                    ?? 0
                                );

                                return [
                                    `Sisa: ${formatRupiah(sisa)}`,
                                    `Sisa persentase: ${nilai.toFixed(1)}%`,
                                ];
                            },

                            footer: (items) => {
                                if (!items.length) {
                                    return '';
                                }

                                const itemTerpakai =
                                    items.find(
                                        (item) => {
                                            return item
                                                .dataset
                                                .label
                                                === 'Terpakai';
                                        }
                                    );

                                if (!itemTerpakai) {
                                    return '';
                                }

                                const indeks =
                                    itemTerpakai.dataIndex;

                                const batas = Number(
                                    itemTerpakai.dataset
                                        .batasPeringatan
                                        ?.[indeks]
                                    ?? 0
                                );

                                const kondisi =
                                    itemTerpakai.dataset
                                        .kondisiAnggaran
                                        ?.[indeks]
                                    ?? '-';

                                return [
                                    `Batas peringatan: ${batas.toFixed(0)}%`,
                                    `Kondisi: ${kondisi}`,
                                ];
                            },
                        },
                    },
                },

                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        suggestedMax: 100,

                        grid: {
                            color:
                                'rgba(148, 163, 184, 0.15)',
                        },

                        ticks: {
                            callback: (value) => {
                                return `${value}%`;
                            },
                        },
                    },

                    y: {
                        stacked: true,

                        grid: {
                            display: false,
                        },

                        ticks: {
                            autoSkip: false,
                        },
                    },
                },
            }
        JS);
    }

    /**
     * Menentukan bulan dan tahun berdasarkan filter.
     */
    private function tentukanPeriode(): array
    {
        $sekarang = now('Asia/Jakarta');

        $tanggal = match ($this->filter) {
            'bulan_lalu' => $sekarang
                ->copy()
                ->subMonthNoOverflow(),

            'bulan_depan' => $sekarang
                ->copy()
                ->addMonthNoOverflow(),

            default => $sekarang,
        };

        return [
            'bulan' => $tanggal->month,
            'tahun' => $tanggal->year,
        ];
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
     * Menentukan kondisi anggaran.
     */
    private function tentukanKondisi(
        float $persentase,
        float $batasPeringatan
    ): string {
        if ($persentase > 100) {
            return 'Melebihi Anggaran';
        }

        if ($persentase >= 100) {
            return 'Anggaran Habis';
        }

        if ($persentase >= $batasPeringatan) {
            return 'Mencapai Batas Peringatan';
        }

        return 'Aman';
    }

    /**
     * Menentukan warna progress berdasarkan kondisi.
     */
    private function tentukanWarna(
        float $persentase,
        float $batasPeringatan
    ): string {
        if ($persentase >= 100) {
            return 'rgba(239, 68, 68, 0.85)';
        }

        if ($persentase >= $batasPeringatan) {
            return 'rgba(245, 158, 11, 0.85)';
        }

        return 'rgba(34, 197, 94, 0.85)';
    }

    /**
     * Data ketika belum ada anggaran.
     */
    private function dataKosong(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Terpakai',
                    'data' => [
                        0,
                    ],
                    'backgroundColor' => [
                        'rgba(148, 163, 184, 0.40)',
                    ],
                    'borderWidth' => 0,
                    'borderRadius' => 6,
                    'barThickness' => 20,
                    'nominalTerpakai' => [
                        0,
                    ],
                    'nominalAnggaran' => [
                        0,
                    ],
                    'batasPeringatan' => [
                        0,
                    ],
                    'kondisiAnggaran' => [
                        'Belum ada anggaran',
                    ],
                ],
                [
                    'label' => 'Sisa',
                    'data' => [
                        100,
                    ],
                    'backgroundColor' =>
                        'rgba(148, 163, 184, 0.15)',
                    'borderWidth' => 0,
                    'borderRadius' => 6,
                    'barThickness' => 20,
                    'nominalSisa' => [
                        0,
                    ],
                    'nominalAnggaran' => [
                        0,
                    ],
                ],
            ],
            'labels' => [
                'Belum ada anggaran pada periode ini',
            ],
        ];
    }
}