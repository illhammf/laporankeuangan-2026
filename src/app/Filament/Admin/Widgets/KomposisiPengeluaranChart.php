<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Transaksi;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KomposisiPengeluaranChart extends ChartWidget
{
    /**
     * Judul chart.
     */
    protected static ?string $heading =
        'Komposisi Pengeluaran';

    /**
     * Urutan widget pada dashboard.
     *
     * Diletakkan setelah ArusKasBulananChart.
     */
    protected static ?int $sort = 4;

    /**
     * Tampil berdampingan dengan chart arus kas
     * pada layar desktop.
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
     * Memperbarui data otomatis setiap 60 detik.
     */
    protected static ?string $pollingInterval = '60s';

    /**
     * Widget langsung dimuat saat dashboard dibuka.
     */
    protected static bool $isLazy = false;

    /**
     * Filter periode awal.
     */
    public ?string $filter = 'bulan_ini';

    /**
     * Deskripsi chart berdasarkan filter aktif.
     */
    public function getDescription(): ?string
    {
        return match ($this->filter) {
            'bulan_lalu' =>
                'Proporsi pengeluaran berdasarkan kategori pada bulan sebelumnya.',

            '3_bulan' =>
                'Proporsi pengeluaran berdasarkan kategori selama tiga bulan terakhir.',

            'tahun_ini' =>
                'Proporsi pengeluaran berdasarkan kategori pada tahun berjalan.',

            default =>
                'Proporsi pengeluaran berdasarkan kategori pada bulan berjalan.',
        };
    }

    /**
     * Pilihan filter periode.
     */
    protected function getFilters(): ?array
    {
        return [
            'bulan_ini' => 'Bulan Ini',
            'bulan_lalu' => 'Bulan Lalu',
            '3_bulan' => '3 Bulan Terakhir',
            'tahun_ini' => 'Tahun Berjalan',
        ];
    }

    /**
     * Menyiapkan data doughnut chart.
     */
    protected function getData(): array
    {
        [
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
        ] = $this->tentukanPeriode();

        $rekapPengeluaran = DB::table('transaksi')
            ->join(
                'kategori',
                'kategori.id',
                '=',
                'transaksi.kategori_id'
            )
            ->leftJoin(
                'kategori as kategori_induk',
                'kategori_induk.id',
                '=',
                'kategori.kategori_induk_id'
            )
            ->selectRaw(
                '
                COALESCE(
                    kategori_induk.id,
                    kategori.id
                ) AS kategori_grup_id
                '
            )
            ->selectRaw(
                '
                COALESCE(
                    kategori_induk.nama_kategori,
                    kategori.nama_kategori
                ) AS nama_kategori
                '
            )
            ->selectRaw(
                "
                MAX(
                    COALESCE(
                        kategori_induk.warna,
                        kategori.warna,
                        '#6B7280'
                    )
                ) AS warna_kategori
                "
            )
            ->selectRaw(
                '
                COALESCE(
                    SUM(transaksi.nominal),
                    0
                ) AS total_pengeluaran
                '
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
            ->whereBetween(
                'transaksi.tanggal_transaksi',
                [
                    $tanggalMulai,
                    $tanggalSelesai,
                ]
            )
            ->groupByRaw(
                '
                COALESCE(
                    kategori_induk.id,
                    kategori.id
                ),
                COALESCE(
                    kategori_induk.nama_kategori,
                    kategori.nama_kategori
                )
                '
            )
            ->orderByDesc('total_pengeluaran')
            ->get();

        if ($rekapPengeluaran->isEmpty()) {
            return $this->dataKosong();
        }

        /*
        |--------------------------------------------------------------------------
        | Pembatasan jumlah bagian chart
        |--------------------------------------------------------------------------
        |
        | Lima kategori terbesar ditampilkan secara langsung.
        | Kategori sisanya digabung menjadi "Kategori Lainnya".
        |
        | Dengan demikian, chart tetap mudah dibaca meskipun jumlah
        | kategori pengeluaran sangat banyak.
        |
        */
        $kategoriUtama = $rekapPengeluaran->take(5);

        $totalKategoriLainnya = (float) $rekapPengeluaran
            ->skip(5)
            ->sum('total_pengeluaran');

        $labels = $kategoriUtama
            ->pluck('nama_kategori')
            ->map(
                fn ($namaKategori): string =>
                    (string) $namaKategori
            )
            ->values()
            ->all();

        $data = $kategoriUtama
            ->pluck('total_pengeluaran')
            ->map(
                fn ($nominal): float =>
                    round((float) $nominal, 2)
            )
            ->values()
            ->all();

        $warna = $kategoriUtama
            ->pluck('warna_kategori')
            ->map(
                fn ($warnaKategori): string =>
                    $this->normalisasiWarna(
                        $warnaKategori
                    )
            )
            ->values()
            ->all();

        if ($totalKategoriLainnya > 0) {
            $labels[] = 'Kategori Lainnya';
            $data[] = round(
                $totalKategoriLainnya,
                2
            );
            $warna[] = '#6B7280';
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Pengeluaran',
                    'data' => $data,
                    'backgroundColor' => $warna,
                    'borderColor' =>
                        'rgba(255, 255, 255, 0.85)',
                    'borderWidth' => 2,
                    'hoverBorderWidth' => 3,
                    'hoverOffset' => 10,
                    'spacing' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Tipe chart.
     */
    protected function getType(): string
    {
        return 'doughnut';
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
                cutout: '68%',

                interaction: {
                    mode: 'nearest',
                    intersect: true,
                },

                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 700,
                },

                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',

                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 16,
                            boxWidth: 10,
                            boxHeight: 10,
                        },
                    },

                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const nilai =
                                    Number(context.raw ?? 0);

                                const semuaNilai =
                                    context.dataset.data ?? [];

                                const total = semuaNilai.reduce(
                                    (jumlah, item) => {
                                        return jumlah
                                            + Number(item ?? 0);
                                    },
                                    0
                                );

                                const persentase =
                                    total > 0
                                        ? (
                                            (nilai / total)
                                            * 100
                                        ).toFixed(1)
                                        : '0.0';

                                const nominal =
                                    new Intl.NumberFormat(
                                        'id-ID',
                                        {
                                            style: 'currency',
                                            currency: 'IDR',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        }
                                    ).format(nilai);

                                return `${context.label}: ${nominal} (${persentase}%)`;
                            },

                            footer: (items) => {
                                if (!items.length) {
                                    return '';
                                }

                                const semuaNilai =
                                    items[0].dataset.data ?? [];

                                const total = semuaNilai.reduce(
                                    (jumlah, item) => {
                                        return jumlah
                                            + Number(item ?? 0);
                                    },
                                    0
                                );

                                const nominal =
                                    new Intl.NumberFormat(
                                        'id-ID',
                                        {
                                            style: 'currency',
                                            currency: 'IDR',
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 0,
                                        }
                                    ).format(total);

                                return `Total pengeluaran: ${nominal}`;
                            },
                        },
                    },
                },
            }
        JS);
    }

    /**
     * Menentukan periode berdasarkan filter.
     */
    private function tentukanPeriode(): array
    {
        $sekarang = now('Asia/Jakarta');

        return match ($this->filter) {
            'bulan_lalu' => [
                'tanggal_mulai' => $sekarang
                    ->copy()
                    ->subMonthNoOverflow()
                    ->startOfMonth(),

                'tanggal_selesai' => $sekarang
                    ->copy()
                    ->subMonthNoOverflow()
                    ->endOfMonth(),
            ],

            '3_bulan' => [
                'tanggal_mulai' => $sekarang
                    ->copy()
                    ->subMonthsNoOverflow(2)
                    ->startOfMonth(),

                'tanggal_selesai' => $sekarang
                    ->copy()
                    ->endOfDay(),
            ],

            'tahun_ini' => [
                'tanggal_mulai' => $sekarang
                    ->copy()
                    ->startOfYear(),

                'tanggal_selesai' => $sekarang
                    ->copy()
                    ->endOfDay(),
            ],

            default => [
                'tanggal_mulai' => $sekarang
                    ->copy()
                    ->startOfMonth(),

                'tanggal_selesai' => $sekarang
                    ->copy()
                    ->endOfDay(),
            ],
        };
    }

    /**
     * Data chart ketika belum ada pengeluaran.
     */
    private function dataKosong(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Total Pengeluaran',
                    'data' => [
                        0,
                    ],
                    'backgroundColor' => [
                        '#D1D5DB',
                    ],
                    'borderColor' => [
                        '#D1D5DB',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => [
                'Belum ada pengeluaran',
            ],
        ];
    }

    /**
     * Memastikan warna kategori menggunakan format hex
     * enam digit yang valid.
     */
    private function normalisasiWarna(
        mixed $warna
    ): string {
        if (
            is_string($warna)
            && preg_match(
                '/^#[0-9A-Fa-f]{6}$/',
                $warna
            ) === 1
        ) {
            return strtoupper($warna);
        }

        return '#6B7280';
    }
}