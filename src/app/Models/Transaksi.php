<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaksi extends Model
{
    use HasFactory;
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Konstanta jenis transaksi
    |--------------------------------------------------------------------------
    */

    public const JENIS_PEMASUKAN = 'pemasukan';

    public const JENIS_PENGELUARAN = 'pengeluaran';

    /*
    |--------------------------------------------------------------------------
    | Konstanta status transaksi
    |--------------------------------------------------------------------------
    */

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_TERTUNDA = 'tertunda';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    /*
    |--------------------------------------------------------------------------
    | Konstanta sumber pencatatan
    |--------------------------------------------------------------------------
    */

    public const SUMBER_MANUAL = 'manual';

    public const SUMBER_OTOMATIS = 'otomatis';

    public const SUMBER_IMPOR = 'impor';

    /**
     * Nama tabel yang digunakan model.
     */
    protected $table = 'transaksi';

    /**
     * Kolom yang dapat diisi menggunakan mass assignment.
     */
    protected $fillable = [
        'pengguna_id',
        'dompet_id',
        'kategori_id',
        'kode_transaksi',
        'jenis_transaksi',
        'tanggal_transaksi',
        'nama_transaksi',
        'nominal',
        'catatan',
        'pihak_terkait',
        'lokasi',
        'bukti_transaksi',
        'status',
        'sumber_pencatatan',
        'transaksi_rutin',
    ];

    /**
     * Konversi tipe data atribut.
     */
    protected $casts = [
        'tanggal_transaksi' => 'datetime',
        'nominal' => 'decimal:2',
        'transaksi_rutin' => 'boolean',
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
     * Daftar jenis transaksi.
     */
    public static function daftarJenisTransaksi(): array
    {
        return [
            self::JENIS_PEMASUKAN => 'Pemasukan',
            self::JENIS_PENGELUARAN => 'Pengeluaran',
        ];
    }

    /**
     * Daftar status transaksi.
     */
    public static function daftarStatus(): array
    {
        return [
            self::STATUS_SELESAI => 'Selesai',
            self::STATUS_TERTUNDA => 'Tertunda',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
        ];
    }

    /**
     * Daftar sumber pencatatan transaksi.
     */
    public static function daftarSumberPencatatan(): array
    {
        return [
            self::SUMBER_MANUAL => 'Manual',
            self::SUMBER_OTOMATIS => 'Otomatis',
            self::SUMBER_IMPOR => 'Impor',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */

    /**
     * Pengguna yang memiliki transaksi.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'pengguna_id'
        );
    }

    /**
     * Dompet yang digunakan dalam transaksi.
     */
    public function dompet(): BelongsTo
    {
        return $this->belongsTo(
            Dompet::class,
            'dompet_id'
        );
    }

    /**
     * Kategori transaksi.
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
     * Mengambil transaksi milik pengguna tertentu.
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
     * Mengambil transaksi pemasukan.
     */
    public function scopePemasukan(Builder $query): Builder
    {
        return $query->where(
            'jenis_transaksi',
            self::JENIS_PEMASUKAN
        );
    }

    /**
     * Mengambil transaksi pengeluaran.
     */
    public function scopePengeluaran(Builder $query): Builder
    {
        return $query->where(
            'jenis_transaksi',
            self::JENIS_PENGELUARAN
        );
    }

    /**
     * Mengambil transaksi berdasarkan jenis tertentu.
     */
    public function scopeJenisTransaksi(
        Builder $query,
        string $jenisTransaksi
    ): Builder {
        return $query->where(
            'jenis_transaksi',
            $jenisTransaksi
        );
    }

    /**
     * Mengambil transaksi yang telah selesai.
     */
    public function scopeSelesai(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_SELESAI
        );
    }

    /**
     * Mengambil transaksi yang masih tertunda.
     */
    public function scopeTertunda(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_TERTUNDA
        );
    }

    /**
     * Mengambil transaksi yang dibatalkan.
     */
    public function scopeDibatalkan(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_DIBATALKAN
        );
    }

    /**
     * Mengambil transaksi berdasarkan status.
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
     * Mengambil transaksi yang memengaruhi saldo.
     *
     * Hanya transaksi berstatus selesai yang dihitung
     * dalam pemasukan, pengeluaran, dan saldo dompet.
     */
    public function scopeMemengaruhiSaldo(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_SELESAI
        );
    }

    /**
     * Mengambil transaksi dari dompet tertentu.
     */
    public function scopeDariDompet(
        Builder $query,
        int $dompetId
    ): Builder {
        return $query->where(
            'dompet_id',
            $dompetId
        );
    }

    /**
     * Mengambil transaksi berdasarkan kategori.
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
     * Mengambil transaksi berdasarkan tanggal tertentu.
     *
     * Format tanggal:
     * YYYY-MM-DD
     */
    public function scopePadaTanggal(
        Builder $query,
        string $tanggal
    ): Builder {
        return $query->whereDate(
            'tanggal_transaksi',
            $tanggal
        );
    }

    /**
     * Mengambil transaksi berdasarkan rentang tanggal.
     *
     * Contoh:
     * 2026-07-01 sampai 2026-07-31.
     */
    public function scopeDalamPeriode(
        Builder $query,
        string $tanggalMulai,
        string $tanggalSelesai
    ): Builder {
        return $query->whereBetween(
            'tanggal_transaksi',
            [
                $tanggalMulai . ' 00:00:00',
                $tanggalSelesai . ' 23:59:59',
            ]
        );
    }

    /**
     * Mengambil transaksi pada bulan dan tahun tertentu.
     */
    public function scopePadaBulan(
        Builder $query,
        int $bulan,
        int $tahun
    ): Builder {
        return $query
            ->whereYear('tanggal_transaksi', $tahun)
            ->whereMonth('tanggal_transaksi', $bulan);
    }

    /**
     * Mengambil transaksi pada tahun tertentu.
     */
    public function scopePadaTahun(
        Builder $query,
        int $tahun
    ): Builder {
        return $query->whereYear(
            'tanggal_transaksi',
            $tahun
        );
    }

    /**
     * Mengambil transaksi rutin.
     */
    public function scopeRutin(Builder $query): Builder
    {
        return $query->where(
            'transaksi_rutin',
            true
        );
    }

    /**
     * Mengambil transaksi yang tidak rutin.
     */
    public function scopeTidakRutin(
        Builder $query
    ): Builder {
        return $query->where(
            'transaksi_rutin',
            false
        );
    }

    /**
     * Mengambil transaksi berdasarkan sumber pencatatan.
     */
    public function scopeSumberPencatatan(
        Builder $query,
        string $sumberPencatatan
    ): Builder {
        return $query->where(
            'sumber_pencatatan',
            $sumberPencatatan
        );
    }

    /**
     * Mencari transaksi berdasarkan kode, nama,
     * pihak terkait, catatan, atau lokasi.
     */
    public function scopeCari(
        Builder $query,
        string $kataKunci
    ): Builder {
        return $query->where(
            function (Builder $subQuery) use ($kataKunci) {
                $subQuery
                    ->where(
                        'kode_transaksi',
                        'like',
                        '%' . $kataKunci . '%'
                    )
                    ->orWhere(
                        'nama_transaksi',
                        'like',
                        '%' . $kataKunci . '%'
                    )
                    ->orWhere(
                        'pihak_terkait',
                        'like',
                        '%' . $kataKunci . '%'
                    )
                    ->orWhere(
                        'catatan',
                        'like',
                        '%' . $kataKunci . '%'
                    )
                    ->orWhere(
                        'lokasi',
                        'like',
                        '%' . $kataKunci . '%'
                    );
            }
        );
    }

    /**
     * Mengurutkan transaksi terbaru.
     */
    public function scopeTerbaru(Builder $query): Builder
    {
        return $query
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id');
    }

    /**
     * Mengurutkan transaksi terlama.
     */
    public function scopeTerlama(Builder $query): Builder
    {
        return $query
            ->orderBy('tanggal_transaksi')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */

    /**
     * Mendapatkan nominal dalam format Rupiah.
     *
     * Contoh:
     * Rp25.000
     */
    public function getNominalRupiahAttribute(): string
    {
        return 'Rp' . number_format(
            (float) $this->nominal,
            0,
            ',',
            '.'
        );
    }

    /**
     * Mendapatkan nominal beserta tanda pemasukan
     * atau pengeluaran.
     *
     * Contoh:
     * + Rp500.000
     * - Rp25.000
     */
    public function getNominalBertandaAttribute(): string
    {
        $tanda = $this->adalahPemasukan()
            ? '+ '
            : '- ';

        return $tanda . $this->nominal_rupiah;
    }

    /**
     * Mendapatkan nama jenis transaksi.
     */
    public function getLabelJenisTransaksiAttribute(): string
    {
        return self::daftarJenisTransaksi()[
            $this->jenis_transaksi
        ] ?? ucfirst($this->jenis_transaksi);
    }

    /**
     * Mendapatkan nama status transaksi.
     */
    public function getLabelStatusAttribute(): string
    {
        return self::daftarStatus()[
            $this->status
        ] ?? ucfirst($this->status);
    }

    /**
     * Mendapatkan nama sumber pencatatan.
     */
    public function getLabelSumberPencatatanAttribute(): string
    {
        return self::daftarSumberPencatatan()[
            $this->sumber_pencatatan
        ] ?? ucfirst($this->sumber_pencatatan);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Memeriksa apakah transaksi merupakan pemasukan.
     */
    public function adalahPemasukan(): bool
    {
        return $this->jenis_transaksi
            === self::JENIS_PEMASUKAN;
    }

    /**
     * Memeriksa apakah transaksi merupakan pengeluaran.
     */
    public function adalahPengeluaran(): bool
    {
        return $this->jenis_transaksi
            === self::JENIS_PENGELUARAN;
    }

    /**
     * Memeriksa apakah transaksi telah selesai.
     */
    public function telahSelesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    /**
     * Memeriksa apakah transaksi masih tertunda.
     */
    public function masihTertunda(): bool
    {
        return $this->status === self::STATUS_TERTUNDA;
    }

    /**
     * Memeriksa apakah transaksi telah dibatalkan.
     */
    public function telahDibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    /**
     * Memeriksa apakah transaksi memengaruhi saldo.
     */
    public function memengaruhiSaldo(): bool
    {
        return $this->telahSelesai()
            && $this->deleted_at === null;
    }

    /**
     * Memeriksa apakah transaksi dicatat secara manual.
     */
    public function dicatatManual(): bool
    {
        return $this->sumber_pencatatan
            === self::SUMBER_MANUAL;
    }

    /**
     * Memeriksa apakah transaksi merupakan transaksi rutin.
     */
    public function adalahTransaksiRutin(): bool
    {
        return $this->transaksi_rutin;
    }

    /**
     * Memeriksa apakah bukti transaksi tersedia.
     */
    public function memilikiBuktiTransaksi(): bool
    {
        return ! empty($this->bukti_transaksi);
    }

    /**
     * Memeriksa kesesuaian jenis transaksi dengan kategori.
     */
    public function kategoriSesuai(): bool
    {
        if (! $this->relationLoaded('kategori')) {
            $this->load('kategori');
        }

        return $this->kategori !== null
            && $this->kategori->jenis_transaksi
                === $this->jenis_transaksi;
    }
}