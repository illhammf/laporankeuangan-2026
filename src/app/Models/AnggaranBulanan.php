<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnggaranBulanan extends Model
{
    use HasFactory;
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Konstanta status anggaran
    |--------------------------------------------------------------------------
    */

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    /**
     * Nama tabel yang digunakan model.
     */
    protected $table = 'anggaran_bulanan';

    /**
     * Kolom yang dapat diisi melalui mass assignment.
     */
    protected $fillable = [
        'pengguna_id',
        'kategori_id',
        'bulan',
        'tahun',
        'nominal_anggaran',
        'batas_peringatan',
        'ulangi_bulan_berikutnya',
        'status',
        'catatan',
    ];

    /**
     * Konversi tipe data atribut.
     */
    protected $casts = [
        'bulan' => 'integer',
        'tahun' => 'integer',
        'nominal_anggaran' => 'decimal:2',
        'batas_peringatan' => 'decimal:2',
        'ulangi_bulan_berikutnya' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Daftar nilai pilihan
    |--------------------------------------------------------------------------
    */

    /**
     * Daftar status anggaran.
     */
    public static function daftarStatus(): array
    {
        return [
            self::STATUS_AKTIF => 'Aktif',
            self::STATUS_SELESAI => 'Selesai',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
        ];
    }

    /**
     * Daftar bulan dalam Bahasa Indonesia.
     */
    public static function daftarBulan(): array
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */

    /**
     * Pengguna yang memiliki anggaran.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'pengguna_id'
        );
    }

    /**
     * Kategori pengeluaran yang menggunakan anggaran.
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(
            Kategori::class,
            'kategori_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scope
    |--------------------------------------------------------------------------
    */

    /**
     * Mengambil anggaran milik pengguna tertentu.
     */
    public function scopeMilikPengguna(
        Builder $query,
        int $penggunaId
    ): Builder {
        return $query->where(
            'pengguna_id',
            $penggunaId
        );
    }

    /**
     * Mengambil anggaran yang masih aktif.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_AKTIF
        );
    }

    /**
     * Mengambil anggaran yang telah selesai.
     */
    public function scopeSelesai(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_SELESAI
        );
    }

    /**
     * Mengambil anggaran yang dibatalkan.
     */
    public function scopeDibatalkan(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_DIBATALKAN
        );
    }

    /**
     * Mengambil anggaran berdasarkan status.
     */
    public function scopeStatus(
        Builder $query,
        string $status
    ): Builder {
        return $query->where(
            'status',
            $status
        );
    }

    /**
     * Mengambil anggaran berdasarkan kategori tertentu.
     */
    public function scopeDenganKategori(
        Builder $query,
        int $kategoriId
    ): Builder {
        return $query->where(
            'kategori_id',
            $kategoriId
        );
    }

    /**
     * Mengambil anggaran pada bulan tertentu.
     */
    public function scopePadaBulan(
        Builder $query,
        int $bulan
    ): Builder {
        return $query->where(
            'bulan',
            $bulan
        );
    }

    /**
     * Mengambil anggaran pada tahun tertentu.
     */
    public function scopePadaTahun(
        Builder $query,
        int $tahun
    ): Builder {
        return $query->where(
            'tahun',
            $tahun
        );
    }

    /**
     * Mengambil anggaran pada bulan dan tahun tertentu.
     */
    public function scopePadaPeriode(
        Builder $query,
        int $bulan,
        int $tahun
    ): Builder {
        return $query
            ->where('bulan', $bulan)
            ->where('tahun', $tahun);
    }

    /**
     * Mengambil anggaran bulan berjalan.
     */
    public function scopeBulanIni(Builder $query): Builder
    {
        return $query
            ->where('bulan', now()->month)
            ->where('tahun', now()->year);
    }

    /**
     * Mengambil anggaran tahun berjalan.
     */
    public function scopeTahunIni(Builder $query): Builder
    {
        return $query->where(
            'tahun',
            now()->year
        );
    }

    /**
     * Mengambil anggaran yang akan diulangi pada bulan berikutnya.
     */
    public function scopeBerulang(Builder $query): Builder
    {
        return $query->where(
            'ulangi_bulan_berikutnya',
            true
        );
    }

    /**
     * Mengambil anggaran yang tidak berulang.
     */
    public function scopeTidakBerulang(
        Builder $query
    ): Builder {
        return $query->where(
            'ulangi_bulan_berikutnya',
            false
        );
    }

    /**
     * Mencari anggaran berdasarkan nama kategori atau catatan.
     */
    public function scopeCari(
        Builder $query,
        string $kataKunci
    ): Builder {
        return $query->where(
            function (Builder $subQuery) use ($kataKunci) {
                $subQuery
                    ->whereHas(
                        'kategori',
                        function (Builder $kategoriQuery) use ($kataKunci) {
                            $kategoriQuery->where(
                                'nama_kategori',
                                'like',
                                '%' . $kataKunci . '%'
                            );
                        }
                    )
                    ->orWhere(
                        'catatan',
                        'like',
                        '%' . $kataKunci . '%'
                    );
            }
        );
    }

    /**
     * Mengurutkan anggaran dari periode terbaru.
     */
    public function scopeTerbaru(Builder $query): Builder
    {
        return $query
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->orderByDesc('id');
    }

    /**
     * Mengurutkan anggaran dari periode terlama.
     */
    public function scopeTerlama(Builder $query): Builder
    {
        return $query
            ->orderBy('tahun')
            ->orderBy('bulan')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */

    /**
     * Mendapatkan nama bulan dalam Bahasa Indonesia.
     */
    public function getNamaBulanAttribute(): string
    {
        return self::daftarBulan()[$this->bulan] ?? '-';
    }

    /**
     * Mendapatkan label periode anggaran.
     *
     * Contoh: Juli 2026.
     */
    public function getLabelPeriodeAttribute(): string
    {
        return $this->nama_bulan . ' ' . $this->tahun;
    }

    /**
     * Mendapatkan nominal anggaran dalam format Rupiah.
     *
     * Contoh: Rp1.000.000.
     */
    public function getNominalAnggaranRupiahAttribute(): string
    {
        return 'Rp' . number_format(
            (float) $this->nominal_anggaran,
            0,
            ',',
            '.'
        );
    }

    /**
     * Mendapatkan batas peringatan dalam format persentase.
     *
     * Contoh: 80%.
     */
    public function getBatasPeringatanLabelAttribute(): string
    {
        return number_format(
            (float) $this->batas_peringatan,
            0,
            ',',
            '.'
        ) . '%';
    }

    /**
     * Mendapatkan label status anggaran.
     */
    public function getLabelStatusAttribute(): string
    {
        return self::daftarStatus()[
            $this->status
        ] ?? ucfirst($this->status);
    }

    /**
     * Mendapatkan total pengeluaran aktual untuk anggaran.
     *
     * Hanya transaksi pengeluaran berstatus selesai yang dihitung.
     */
    public function getTotalTerpakaiAttribute(): string
    {
        $total = Transaksi::query()
            ->milikPengguna($this->pengguna_id)
            ->denganKategori($this->kategori_id)
            ->pengeluaran()
            ->selesai()
            ->padaBulan($this->bulan, $this->tahun)
            ->sum('nominal');

        return number_format(
            (float) $total,
            2,
            '.',
            ''
        );
    }

    /**
     * Mendapatkan total penggunaan dalam format Rupiah.
     */
    public function getTotalTerpakaiRupiahAttribute(): string
    {
        return 'Rp' . number_format(
            (float) $this->total_terpakai,
            0,
            ',',
            '.'
        );
    }

    /**
     * Mendapatkan sisa anggaran.
     */
    public function getSisaAnggaranAttribute(): string
    {
        $sisa = (float) $this->nominal_anggaran
            - (float) $this->total_terpakai;

        return number_format(
            $sisa,
            2,
            '.',
            ''
        );
    }

    /**
     * Mendapatkan sisa anggaran dalam format Rupiah.
     */
    public function getSisaAnggaranRupiahAttribute(): string
    {
        $sisa = (float) $this->sisa_anggaran;

        $awalan = $sisa < 0 ? '-Rp' : 'Rp';

        return $awalan . number_format(
            abs($sisa),
            0,
            ',',
            '.'
        );
    }

    /**
     * Mendapatkan persentase penggunaan anggaran.
     */
    public function getPersentasePenggunaanAttribute(): float
    {
        $nominalAnggaran = (float) $this->nominal_anggaran;

        if ($nominalAnggaran <= 0) {
            return 0;
        }

        return round(
            ((float) $this->total_terpakai / $nominalAnggaran) * 100,
            2
        );
    }

    /**
     * Mendapatkan persentase penggunaan dalam bentuk label.
     */
    public function getPersentasePenggunaanLabelAttribute(): string
    {
        return number_format(
            $this->persentase_penggunaan,
            2,
            ',',
            '.'
        ) . '%';
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Memeriksa apakah anggaran masih aktif.
     */
    public function masihAktif(): bool
    {
        return $this->status === self::STATUS_AKTIF
            && $this->deleted_at === null;
    }

    /**
     * Memeriksa apakah anggaran telah selesai.
     */
    public function telahSelesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    /**
     * Memeriksa apakah anggaran dibatalkan.
     */
    public function telahDibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    /**
     * Memeriksa apakah anggaran merupakan anggaran bulan berjalan.
     */
    public function adalahBulanIni(): bool
    {
        return $this->bulan === now()->month
            && $this->tahun === now()->year;
    }

    /**
     * Memeriksa apakah anggaran berasal dari periode lampau.
     */
    public function telahLewat(): bool
    {
        if ($this->tahun < now()->year) {
            return true;
        }

        return $this->tahun === now()->year
            && $this->bulan < now()->month;
    }

    /**
     * Memeriksa apakah anggaran berasal dari periode mendatang.
     */
    public function periodeMendatang(): bool
    {
        if ($this->tahun > now()->year) {
            return true;
        }

        return $this->tahun === now()->year
            && $this->bulan > now()->month;
    }

    /**
     * Memeriksa apakah peringatan anggaran sudah harus ditampilkan.
     */
    public function mencapaiBatasPeringatan(): bool
    {
        return $this->persentase_penggunaan
            >= (float) $this->batas_peringatan;
    }

    /**
     * Memeriksa apakah nominal anggaran telah habis.
     */
    public function anggaranHabis(): bool
    {
        return $this->persentase_penggunaan >= 100;
    }

    /**
     * Memeriksa apakah penggunaan melebihi anggaran.
     */
    public function melebihiAnggaran(): bool
    {
        return (float) $this->total_terpakai
            > (float) $this->nominal_anggaran;
    }

    /**
     * Memeriksa apakah anggaran akan diulangi.
     */
    public function akanDiulangi(): bool
    {
        return $this->ulangi_bulan_berikutnya;
    }

    /**
     * Memeriksa apakah nominal anggaran valid.
     */
    public function nominalValid(): bool
    {
        return (float) $this->nominal_anggaran > 0;
    }

    /**
     * Memeriksa apakah batas peringatan valid.
     */
    public function batasPeringatanValid(): bool
    {
        $batas = (float) $this->batas_peringatan;

        return $batas >= 0 && $batas <= 100;
    }
}