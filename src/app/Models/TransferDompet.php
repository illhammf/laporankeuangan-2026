<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferDompet extends Model
{
    use HasFactory;
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Konstanta status transfer
    |--------------------------------------------------------------------------
    */

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_TERTUNDA = 'tertunda';

    public const STATUS_GAGAL = 'gagal';

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
    protected $table = 'transfer_dompet';

    /**
     * Kolom yang dapat diisi melalui mass assignment.
     */
    protected $fillable = [
        'pengguna_id',
        'dompet_asal_id',
        'dompet_tujuan_id',
        'kode_transfer',
        'tanggal_transfer',
        'nominal',
        'biaya_admin',
        'catatan',
        'bukti_transfer',
        'status',
        'sumber_pencatatan',
        'diselesaikan_pada',
    ];

    /**
     * Konversi tipe data atribut.
     */
    protected $casts = [
        'tanggal_transfer' => 'datetime',
        'nominal' => 'decimal:2',
        'biaya_admin' => 'decimal:2',
        'diselesaikan_pada' => 'datetime',
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
     * Daftar status transfer.
     */
    public static function daftarStatus(): array
    {
        return [
            self::STATUS_SELESAI => 'Selesai',
            self::STATUS_TERTUNDA => 'Tertunda',
            self::STATUS_GAGAL => 'Gagal',
            self::STATUS_DIBATALKAN => 'Dibatalkan',
        ];
    }

    /**
     * Daftar sumber pencatatan.
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
     * Pengguna yang memiliki data transfer.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'pengguna_id'
        );
    }

    /**
     * Dompet asal transfer.
     */
    public function dompetAsal(): BelongsTo
    {
        return $this->belongsTo(
            Dompet::class,
            'dompet_asal_id'
        );
    }

    /**
     * Dompet tujuan transfer.
     */
    public function dompetTujuan(): BelongsTo
    {
        return $this->belongsTo(
            Dompet::class,
            'dompet_tujuan_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scope
    |--------------------------------------------------------------------------
    */

    /**
     * Mengambil transfer milik pengguna tertentu.
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
     * Mengambil transfer yang telah selesai.
     */
    public function scopeSelesai(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_SELESAI
        );
    }

    /**
     * Mengambil transfer yang masih tertunda.
     */
    public function scopeTertunda(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_TERTUNDA
        );
    }

    /**
     * Mengambil transfer yang gagal.
     */
    public function scopeGagal(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_GAGAL
        );
    }

    /**
     * Mengambil transfer yang dibatalkan.
     */
    public function scopeDibatalkan(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_DIBATALKAN
        );
    }

    /**
     * Mengambil transfer berdasarkan status.
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
     * Mengambil transfer yang memengaruhi saldo.
     *
     * Hanya transfer selesai yang mengurangi saldo dompet asal
     * dan menambah saldo dompet tujuan.
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
     * Mengambil transfer keluar dari dompet tertentu.
     */
    public function scopeDariDompet(
        Builder $query,
        int $dompetId
    ): Builder {
        return $query->where(
            'dompet_asal_id',
            $dompetId
        );
    }

    /**
     * Mengambil transfer masuk ke dompet tertentu.
     */
    public function scopeKeDompet(
        Builder $query,
        int $dompetId
    ): Builder {
        return $query->where(
            'dompet_tujuan_id',
            $dompetId
        );
    }

    /**
     * Mengambil seluruh transfer yang melibatkan dompet tertentu.
     */
    public function scopeMelibatkanDompet(
        Builder $query,
        int $dompetId
    ): Builder {
        return $query->where(
            function (Builder $subQuery) use ($dompetId) {
                $subQuery
                    ->where('dompet_asal_id', $dompetId)
                    ->orWhere('dompet_tujuan_id', $dompetId);
            }
        );
    }

    /**
     * Mengambil transfer pada tanggal tertentu.
     *
     * Format tanggal: YYYY-MM-DD.
     */
    public function scopePadaTanggal(
        Builder $query,
        string $tanggal
    ): Builder {
        return $query->whereDate(
            'tanggal_transfer',
            $tanggal
        );
    }

    /**
     * Mengambil transfer dalam rentang tanggal tertentu.
     */
    public function scopeDalamPeriode(
        Builder $query,
        string $tanggalMulai,
        string $tanggalSelesai
    ): Builder {
        return $query->whereBetween(
            'tanggal_transfer',
            [
                $tanggalMulai . ' 00:00:00',
                $tanggalSelesai . ' 23:59:59',
            ]
        );
    }

    /**
     * Mengambil transfer pada bulan dan tahun tertentu.
     */
    public function scopePadaBulan(
        Builder $query,
        int $bulan,
        int $tahun
    ): Builder {
        return $query
            ->whereYear('tanggal_transfer', $tahun)
            ->whereMonth('tanggal_transfer', $bulan);
    }

    /**
     * Mengambil transfer pada tahun tertentu.
     */
    public function scopePadaTahun(
        Builder $query,
        int $tahun
    ): Builder {
        return $query->whereYear(
            'tanggal_transfer',
            $tahun
        );
    }

    /**
     * Mengambil transfer berdasarkan sumber pencatatan.
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
     * Mencari transfer berdasarkan kode atau catatan.
     */
    public function scopeCari(
        Builder $query,
        string $kataKunci
    ): Builder {
        return $query->where(
            function (Builder $subQuery) use ($kataKunci) {
                $subQuery
                    ->where(
                        'kode_transfer',
                        'like',
                        '%' . $kataKunci . '%'
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
     * Mengurutkan transfer dari yang terbaru.
     */
    public function scopeTerbaru(Builder $query): Builder
    {
        return $query
            ->orderByDesc('tanggal_transfer')
            ->orderByDesc('id');
    }

    /**
     * Mengurutkan transfer dari yang terlama.
     */
    public function scopeTerlama(Builder $query): Builder
    {
        return $query
            ->orderBy('tanggal_transfer')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */

    /**
     * Mendapatkan nominal transfer dalam format Rupiah.
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
     * Mendapatkan biaya administrasi dalam format Rupiah.
     */
    public function getBiayaAdminRupiahAttribute(): string
    {
        return 'Rp' . number_format(
            (float) $this->biaya_admin,
            0,
            ',',
            '.'
        );
    }

    /**
     * Mendapatkan total pengurangan saldo dompet asal.
     *
     * Total pengurangan = nominal transfer + biaya administrasi.
     */
    public function getTotalPotonganAttribute(): string
    {
        return number_format(
            (float) $this->nominal + (float) $this->biaya_admin,
            2,
            '.',
            ''
        );
    }

    /**
     * Mendapatkan total pengurangan saldo dalam format Rupiah.
     */
    public function getTotalPotonganRupiahAttribute(): string
    {
        return 'Rp' . number_format(
            (float) $this->total_potongan,
            0,
            ',',
            '.'
        );
    }

    /**
     * Mendapatkan label status transfer.
     */
    public function getLabelStatusAttribute(): string
    {
        return self::daftarStatus()[
            $this->status
        ] ?? ucfirst($this->status);
    }

    /**
     * Mendapatkan label sumber pencatatan.
     */
    public function getLabelSumberPencatatanAttribute(): string
    {
        return self::daftarSumberPencatatan()[
            $this->sumber_pencatatan
        ] ?? ucfirst($this->sumber_pencatatan);
    }

    /**
     * Mendapatkan ringkasan arah transfer.
     *
     * Contoh: BRI → DANA.
     */
    public function getArahTransferAttribute(): string
    {
        if (! $this->relationLoaded('dompetAsal')) {
            $this->load('dompetAsal');
        }

        if (! $this->relationLoaded('dompetTujuan')) {
            $this->load('dompetTujuan');
        }

        $asal = $this->dompetAsal?->nama_dompet ?? '-';
        $tujuan = $this->dompetTujuan?->nama_dompet ?? '-';

        return $asal . ' → ' . $tujuan;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Memeriksa apakah transfer telah selesai.
     */
    public function telahSelesai(): bool
    {
        return $this->status === self::STATUS_SELESAI;
    }

    /**
     * Memeriksa apakah transfer masih tertunda.
     */
    public function masihTertunda(): bool
    {
        return $this->status === self::STATUS_TERTUNDA;
    }

    /**
     * Memeriksa apakah transfer gagal.
     */
    public function telahGagal(): bool
    {
        return $this->status === self::STATUS_GAGAL;
    }

    /**
     * Memeriksa apakah transfer dibatalkan.
     */
    public function telahDibatalkan(): bool
    {
        return $this->status === self::STATUS_DIBATALKAN;
    }

    /**
     * Memeriksa apakah transfer memengaruhi saldo.
     */
    public function memengaruhiSaldo(): bool
    {
        return $this->telahSelesai()
            && $this->deleted_at === null;
    }

    /**
     * Memeriksa apakah transfer memiliki biaya administrasi.
     */
    public function memilikiBiayaAdmin(): bool
    {
        return (float) $this->biaya_admin > 0;
    }

    /**
     * Memeriksa apakah tersedia bukti transfer.
     */
    public function memilikiBuktiTransfer(): bool
    {
        return ! empty($this->bukti_transfer);
    }

    /**
     * Memeriksa apakah dompet asal dan tujuan berbeda.
     */
    public function dompetBerbeda(): bool
    {
        return $this->dompet_asal_id
            !== $this->dompet_tujuan_id;
    }

    /**
     * Memeriksa apakah transfer dicatat secara manual.
     */
    public function dicatatManual(): bool
    {
        return $this->sumber_pencatatan
            === self::SUMBER_MANUAL;
    }

    /**
     * Memeriksa apakah transfer dapat diproses.
     */
    public function dapatDiproses(): bool
    {
        return $this->masihTertunda()
            && $this->dompetBerbeda()
            && (float) $this->nominal > 0
            && $this->deleted_at === null;
    }
}