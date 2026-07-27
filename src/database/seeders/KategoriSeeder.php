<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriSeeder extends Seeder
{
    /**
     * Membuat kategori pemasukan dan pengeluaran bawaan
     * untuk seluruh pengguna yang tersedia.
     */
    public function run(): void
    {
        $daftarPengguna = User::query()->get();

        if ($daftarPengguna->isEmpty()) {
            $this->command?->warn(
                'KategoriSeeder dilewati karena belum ada pengguna pada tabel users.'
            );

            return;
        }

        $kategoriPemasukan = $this->kategoriPemasukan();
        $kategoriPengeluaran = $this->kategoriPengeluaran();

        $jumlahKategori = 0;

        DB::transaction(function () use (
            $daftarPengguna,
            $kategoriPemasukan,
            $kategoriPengeluaran,
            &$jumlahKategori
        ): void {
            foreach ($daftarPengguna as $pengguna) {
                $jumlahKategori += $this->simpanDaftarKategori(
                    penggunaId: $pengguna->id,
                    jenisTransaksi: 'pemasukan',
                    daftarKategori: $kategoriPemasukan
                );

                $jumlahKategori += $this->simpanDaftarKategori(
                    penggunaId: $pengguna->id,
                    jenisTransaksi: 'pengeluaran',
                    daftarKategori: $kategoriPengeluaran
                );
            }
        });

        $this->command?->info(
            "{$jumlahKategori} kategori berhasil dibuat atau diperbarui."
        );
    }

    /**
     * Menyimpan kategori utama beserta subkategorinya.
     */
    private function simpanDaftarKategori(
        int $penggunaId,
        string $jenisTransaksi,
        array $daftarKategori
    ): int {
        $jumlahKategori = 0;

        foreach ($daftarKategori as $urutan => $dataKategori) {
            $kategoriUtama = $this->simpanKategori(
                penggunaId: $penggunaId,
                kategoriIndukId: null,
                jenisTransaksi: $jenisTransaksi,
                dataKategori: $dataKategori,
                urutan: $urutan + 1
            );

            $jumlahKategori++;

            foreach (
                $dataKategori['subkategori'] ?? []
                as $urutanSubkategori => $dataSubkategori
            ) {
                $this->simpanKategori(
                    penggunaId: $penggunaId,
                    kategoriIndukId: $kategoriUtama->id,
                    jenisTransaksi: $jenisTransaksi,
                    dataKategori: $dataSubkategori,
                    urutan: $urutanSubkategori + 1
                );

                $jumlahKategori++;
            }
        }

        return $jumlahKategori;
    }

    /**
     * Membuat, memperbarui, atau memulihkan kategori.
     */
    private function simpanKategori(
        int $penggunaId,
        ?int $kategoriIndukId,
        string $jenisTransaksi,
        array $dataKategori,
        int $urutan
    ): Kategori {
        $kategori = Kategori::withTrashed()
            ->where('pengguna_id', $penggunaId)
            ->where('jenis_transaksi', $jenisTransaksi)
            ->where('nama_kategori', $dataKategori['nama_kategori'])
            ->first();

        if ($kategori === null) {
            $kategori = new Kategori();
        }

        if ($kategori->trashed()) {
            $kategori->restore();
        }

        $kategori->fill([
            'pengguna_id' => $penggunaId,
            'kategori_induk_id' => $kategoriIndukId,
            'nama_kategori' => $dataKategori['nama_kategori'],
            'kode_kategori' => $dataKategori['kode_kategori'],
            'jenis_transaksi' => $jenisTransaksi,
            'deskripsi' => $dataKategori['deskripsi'] ?? null,
            'ikon' => $dataKategori['ikon'] ?? null,
            'warna' => $dataKategori['warna'] ?? null,
            'urutan' => $urutan,
            'kategori_bawaan' => true,
            'aktif' => true,
        ]);

        $kategori->save();

        return $kategori;
    }

    /**
     * Daftar kategori pemasukan bawaan.
     */
    private function kategoriPemasukan(): array
    {
        return [
            [
                'nama_kategori' => 'Gaji',
                'kode_kategori' => 'PM-GAJI',
                'deskripsi' => 'Pendapatan tetap dari pekerjaan utama.',
                'ikon' => 'heroicon-o-banknotes',
                'warna' => '#16A34A',
            ],
            [
                'nama_kategori' => 'Uang Saku',
                'kode_kategori' => 'PM-UANG-SAKU',
                'deskripsi' => 'Pemasukan berupa uang saku atau pemberian rutin.',
                'ikon' => 'heroicon-o-wallet',
                'warna' => '#22C55E',
            ],
            [
                'nama_kategori' => 'Freelance',
                'kode_kategori' => 'PM-FREELANCE',
                'deskripsi' => 'Pendapatan dari pekerjaan lepas atau proyek.',
                'ikon' => 'heroicon-o-briefcase',
                'warna' => '#059669',
            ],
            [
                'nama_kategori' => 'Usaha',
                'kode_kategori' => 'PM-USAHA',
                'deskripsi' => 'Pendapatan yang diperoleh dari kegiatan usaha.',
                'ikon' => 'heroicon-o-building-storefront',
                'warna' => '#0D9488',
            ],
            [
                'nama_kategori' => 'Bonus',
                'kode_kategori' => 'PM-BONUS',
                'deskripsi' => 'Bonus, insentif, atau tambahan penghasilan.',
                'ikon' => 'heroicon-o-gift',
                'warna' => '#14B8A6',
            ],
            [
                'nama_kategori' => 'Penjualan',
                'kode_kategori' => 'PM-PENJUALAN',
                'deskripsi' => 'Pemasukan dari penjualan barang atau aset.',
                'ikon' => 'heroicon-o-shopping-bag',
                'warna' => '#10B981',
            ],
            [
                'nama_kategori' => 'Hadiah',
                'kode_kategori' => 'PM-HADIAH',
                'deskripsi' => 'Uang atau hadiah yang diterima dari pihak lain.',
                'ikon' => 'heroicon-o-gift-top',
                'warna' => '#34D399',
            ],
            [
                'nama_kategori' => 'Pengembalian Dana',
                'kode_kategori' => 'PM-REFUND',
                'deskripsi' => 'Dana yang diterima kembali dari pembatalan atau pengembalian transaksi.',
                'ikon' => 'heroicon-o-arrow-uturn-left',
                'warna' => '#2DD4BF',
            ],
            [
                'nama_kategori' => 'Hasil Investasi',
                'kode_kategori' => 'PM-INVESTASI',
                'deskripsi' => 'Keuntungan, dividen, atau hasil investasi.',
                'ikon' => 'heroicon-o-chart-bar-square',
                'warna' => '#4ADE80',
            ],
            [
                'nama_kategori' => 'Pemasukan Lainnya',
                'kode_kategori' => 'PM-LAINNYA',
                'deskripsi' => 'Pemasukan yang tidak termasuk kategori lainnya.',
                'ikon' => 'heroicon-o-ellipsis-horizontal-circle',
                'warna' => '#6B7280',
            ],
        ];
    }

    /**
     * Daftar kategori pengeluaran bawaan.
     */
    private function kategoriPengeluaran(): array
    {
        return [
            [
                'nama_kategori' => 'Makanan dan Minuman',
                'kode_kategori' => 'PG-MAKANAN',
                'deskripsi' => 'Pengeluaran untuk makanan, minuman, dan kebutuhan konsumsi.',
                'ikon' => 'heroicon-o-cake',
                'warna' => '#F97316',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Makan Harian',
                        'kode_kategori' => 'PG-MAKAN-HARIAN',
                        'deskripsi' => 'Pengeluaran makan sehari-hari.',
                        'ikon' => 'heroicon-o-cake',
                        'warna' => '#FB923C',
                    ],
                    [
                        'nama_kategori' => 'Jajan dan Camilan',
                        'kode_kategori' => 'PG-JAJAN',
                        'deskripsi' => 'Pengeluaran untuk jajan dan makanan ringan.',
                        'ikon' => 'heroicon-o-shopping-bag',
                        'warna' => '#FDBA74',
                    ],
                    [
                        'nama_kategori' => 'Kopi dan Minuman',
                        'kode_kategori' => 'PG-MINUMAN',
                        'deskripsi' => 'Pengeluaran untuk kopi dan minuman lainnya.',
                        'ikon' => 'heroicon-o-cake',
                        'warna' => '#EA580C',
                    ],
                    [
                        'nama_kategori' => 'Makan di Luar',
                        'kode_kategori' => 'PG-MAKAN-LUAR',
                        'deskripsi' => 'Pengeluaran makan di restoran atau tempat makan.',
                        'ikon' => 'heroicon-o-building-storefront',
                        'warna' => '#C2410C',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Transportasi',
                'kode_kategori' => 'PG-TRANSPORTASI',
                'deskripsi' => 'Pengeluaran perjalanan dan kendaraan.',
                'ikon' => 'heroicon-o-truck',
                'warna' => '#2563EB',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Bensin',
                        'kode_kategori' => 'PG-BENSIN',
                        'deskripsi' => 'Pembelian bahan bakar kendaraan.',
                        'ikon' => 'heroicon-o-truck',
                        'warna' => '#3B82F6',
                    ],
                    [
                        'nama_kategori' => 'Transportasi Online',
                        'kode_kategori' => 'PG-TRANSPORTASI-ONLINE',
                        'deskripsi' => 'Pengeluaran untuk ojek atau taksi online.',
                        'ikon' => 'heroicon-o-device-phone-mobile',
                        'warna' => '#60A5FA',
                    ],
                    [
                        'nama_kategori' => 'Parkir dan Tol',
                        'kode_kategori' => 'PG-PARKIR-TOL',
                        'deskripsi' => 'Biaya parkir dan penggunaan jalan tol.',
                        'ikon' => 'heroicon-o-ticket',
                        'warna' => '#1D4ED8',
                    ],
                    [
                        'nama_kategori' => 'Servis Kendaraan',
                        'kode_kategori' => 'PG-SERVIS-KENDARAAN',
                        'deskripsi' => 'Biaya servis dan perawatan kendaraan.',
                        'ikon' => 'heroicon-o-wrench-screwdriver',
                        'warna' => '#1E40AF',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Belanja',
                'kode_kategori' => 'PG-BELANJA',
                'deskripsi' => 'Pengeluaran untuk pembelian barang pribadi.',
                'ikon' => 'heroicon-o-shopping-cart',
                'warna' => '#EC4899',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Kebutuhan Pribadi',
                        'kode_kategori' => 'PG-KEBUTUHAN-PRIBADI',
                        'deskripsi' => 'Pembelian kebutuhan pribadi sehari-hari.',
                        'ikon' => 'heroicon-o-user',
                        'warna' => '#F472B6',
                    ],
                    [
                        'nama_kategori' => 'Pakaian',
                        'kode_kategori' => 'PG-PAKAIAN',
                        'deskripsi' => 'Pembelian pakaian, sepatu, atau aksesori.',
                        'ikon' => 'heroicon-o-shopping-bag',
                        'warna' => '#DB2777',
                    ],
                    [
                        'nama_kategori' => 'Elektronik',
                        'kode_kategori' => 'PG-ELEKTRONIK',
                        'deskripsi' => 'Pembelian perangkat atau aksesori elektronik.',
                        'ikon' => 'heroicon-o-computer-desktop',
                        'warna' => '#BE185D',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Tagihan',
                'kode_kategori' => 'PG-TAGIHAN',
                'deskripsi' => 'Pengeluaran untuk tagihan rutin.',
                'ikon' => 'heroicon-o-document-currency-dollar',
                'warna' => '#7C3AED',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Listrik',
                        'kode_kategori' => 'PG-LISTRIK',
                        'deskripsi' => 'Pembayaran tagihan listrik.',
                        'ikon' => 'heroicon-o-bolt',
                        'warna' => '#8B5CF6',
                    ],
                    [
                        'nama_kategori' => 'Air',
                        'kode_kategori' => 'PG-AIR',
                        'deskripsi' => 'Pembayaran tagihan air.',
                        'ikon' => 'heroicon-o-beaker',
                        'warna' => '#A78BFA',
                    ],
                    [
                        'nama_kategori' => 'Internet',
                        'kode_kategori' => 'PG-INTERNET',
                        'deskripsi' => 'Pembayaran internet atau Wi-Fi.',
                        'ikon' => 'heroicon-o-wifi',
                        'warna' => '#6D28D9',
                    ],
                    [
                        'nama_kategori' => 'Pulsa dan Paket Data',
                        'kode_kategori' => 'PG-PULSA-DATA',
                        'deskripsi' => 'Pembelian pulsa dan paket internet.',
                        'ikon' => 'heroicon-o-device-phone-mobile',
                        'warna' => '#5B21B6',
                    ],
                    [
                        'nama_kategori' => 'Langganan Digital',
                        'kode_kategori' => 'PG-LANGGANAN',
                        'deskripsi' => 'Pembayaran aplikasi atau layanan berlangganan.',
                        'ikon' => 'heroicon-o-arrow-path',
                        'warna' => '#9333EA',
                    ],
                    [
                        'nama_kategori' => 'Biaya Administrasi',
                        'kode_kategori' => 'PG-BIAYA-ADMIN',
                        'deskripsi' => 'Biaya administrasi bank atau layanan keuangan.',
                        'ikon' => 'heroicon-o-receipt-percent',
                        'warna' => '#C084FC',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Pendidikan',
                'kode_kategori' => 'PG-PENDIDIKAN',
                'deskripsi' => 'Pengeluaran untuk pendidikan dan pembelajaran.',
                'ikon' => 'heroicon-o-academic-cap',
                'warna' => '#0891B2',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Biaya Kuliah atau Kursus',
                        'kode_kategori' => 'PG-KULIAH-KURSUS',
                        'deskripsi' => 'Pembayaran kuliah, pelatihan, atau kursus.',
                        'ikon' => 'heroicon-o-academic-cap',
                        'warna' => '#06B6D4',
                    ],
                    [
                        'nama_kategori' => 'Buku dan Alat Tulis',
                        'kode_kategori' => 'PG-BUKU-ATK',
                        'deskripsi' => 'Pembelian buku dan perlengkapan belajar.',
                        'ikon' => 'heroicon-o-book-open',
                        'warna' => '#22D3EE',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Kesehatan',
                'kode_kategori' => 'PG-KESEHATAN',
                'deskripsi' => 'Pengeluaran untuk kesehatan dan perawatan.',
                'ikon' => 'heroicon-o-heart',
                'warna' => '#DC2626',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Obat dan Vitamin',
                        'kode_kategori' => 'PG-OBAT-VITAMIN',
                        'deskripsi' => 'Pembelian obat, vitamin, atau suplemen.',
                        'ikon' => 'heroicon-o-plus-circle',
                        'warna' => '#EF4444',
                    ],
                    [
                        'nama_kategori' => 'Pemeriksaan Kesehatan',
                        'kode_kategori' => 'PG-PEMERIKSAAN',
                        'deskripsi' => 'Biaya konsultasi dan pemeriksaan kesehatan.',
                        'ikon' => 'heroicon-o-heart',
                        'warna' => '#F87171',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Hiburan',
                'kode_kategori' => 'PG-HIBURAN',
                'deskripsi' => 'Pengeluaran untuk rekreasi dan hiburan.',
                'ikon' => 'heroicon-o-film',
                'warna' => '#D946EF',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Nongkrong',
                        'kode_kategori' => 'PG-NONGKRONG',
                        'deskripsi' => 'Pengeluaran untuk berkumpul atau nongkrong.',
                        'ikon' => 'heroicon-o-user-group',
                        'warna' => '#E879F9',
                    ],
                    [
                        'nama_kategori' => 'Film dan Game',
                        'kode_kategori' => 'PG-FILM-GAME',
                        'deskripsi' => 'Pengeluaran untuk film, game, dan hiburan digital.',
                        'ikon' => 'heroicon-o-play-circle',
                        'warna' => '#C026D3',
                    ],
                    [
                        'nama_kategori' => 'Liburan',
                        'kode_kategori' => 'PG-LIBURAN',
                        'deskripsi' => 'Pengeluaran untuk rekreasi dan perjalanan liburan.',
                        'ikon' => 'heroicon-o-map',
                        'warna' => '#A21CAF',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Kebutuhan Rumah',
                'kode_kategori' => 'PG-RUMAH',
                'deskripsi' => 'Pengeluaran untuk kebutuhan rumah tangga.',
                'ikon' => 'heroicon-o-home',
                'warna' => '#CA8A04',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Belanja Bulanan',
                        'kode_kategori' => 'PG-BELANJA-BULANAN',
                        'deskripsi' => 'Belanja kebutuhan rumah tangga bulanan.',
                        'ikon' => 'heroicon-o-shopping-cart',
                        'warna' => '#EAB308',
                    ],
                    [
                        'nama_kategori' => 'Perawatan Rumah',
                        'kode_kategori' => 'PG-PERAWATAN-RUMAH',
                        'deskripsi' => 'Pengeluaran untuk perbaikan dan perawatan rumah.',
                        'ikon' => 'heroicon-o-home-modern',
                        'warna' => '#FACC15',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Sosial dan Ibadah',
                'kode_kategori' => 'PG-SOSIAL',
                'deskripsi' => 'Pengeluaran untuk kegiatan sosial dan ibadah.',
                'ikon' => 'heroicon-o-hand-raised',
                'warna' => '#0F766E',
                'subkategori' => [
                    [
                        'nama_kategori' => 'Sedekah dan Donasi',
                        'kode_kategori' => 'PG-SEDEKAH',
                        'deskripsi' => 'Sedekah, donasi, atau bantuan sosial.',
                        'ikon' => 'heroicon-o-heart',
                        'warna' => '#14B8A6',
                    ],
                    [
                        'nama_kategori' => 'Hadiah untuk Orang Lain',
                        'kode_kategori' => 'PG-HADIAH-ORANG-LAIN',
                        'deskripsi' => 'Pembelian hadiah untuk keluarga atau orang lain.',
                        'ikon' => 'heroicon-o-gift',
                        'warna' => '#2DD4BF',
                    ],
                ],
            ],
            [
                'nama_kategori' => 'Pengeluaran Lainnya',
                'kode_kategori' => 'PG-LAINNYA',
                'deskripsi' => 'Pengeluaran yang tidak termasuk kategori lainnya.',
                'ikon' => 'heroicon-o-ellipsis-horizontal-circle',
                'warna' => '#6B7280',
            ],
        ];
    }
}