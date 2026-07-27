<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dompet extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Nama tabel yang digunakan model.
     */
    protected $table = 'dompet';

    /**
     * Kolom yang dapat diisi melalui mass assignment.
     */
    protected $fillable = [
        'pengguna_id',
        'nama_dompet',
        'jenis_dompet',
        'nomor_akun',
        'saldo_awal',
        'mata_uang',
        'ikon',
        'warna',
        'urutan',
        'aktif',
        'catatan',
    ];

    /**
     * Konversi tipe data atribut.
     */
    protected $casts = [
        'saldo_awal' => 'decimal:2',
        'urutan' => 'integer',
        'aktif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */

    /**
     * Pengguna yang memiliki dompet.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'pengguna_id'
        );
    }

    /**
     * Seluruh transaksi yang menggunakan dompet.
     */
    public function transaksi(): HasMany
    {
        return $this->hasMany(
            Transaksi::class,
            'dompet_id'
        );
    }

    /**
     * Seluruh transfer yang keluar dari dompet.
     */
    public function transferKeluar(): HasMany
    {
        return $this->hasMany(
            TransferDompet::class,
            'dompet_asal_id'
        );
    }

    /**
     * Seluruh transfer yang masuk ke dompet.
     */
    public function transferMasuk(): HasMany
    {
        return $this->hasMany(
            TransferDompet::class,
            'dompet_tujuan_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scope
    |--------------------------------------------------------------------------
    */

    /**
     * Mengambil dompet yang masih aktif.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    /**
     * Mengambil dompet milik pengguna tertentu.
     */
    public function scopeMilikPengguna(
        Builder $query,
        int $penggunaId
    ): Builder {
        return $query->where('pengguna_id', $penggunaId);
    }

    /**
     * Mengambil dompet berdasarkan jenis tertentu.
     */
    public function scopeJenis(
        Builder $query,
        string $jenisDompet
    ): Builder {
        return $query->where('jenis_dompet', $jenisDompet);
    }

    /**
     * Mengurutkan dompet berdasarkan kolom urutan dan nama.
     */
    public function scopeTerurut(Builder $query): Builder
    {
        return $query
            ->orderBy('urutan')
            ->orderBy('nama_dompet');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Memeriksa apakah dompet merupakan dompet tunai.
     */
    public function adalahTunai(): bool
    {
        return $this->jenis_dompet === 'tunai';
    }

    /**
     * Memeriksa apakah dompet merupakan rekening bank.
     */
    public function adalahBank(): bool
    {
        return $this->jenis_dompet === 'bank';
    }

    /**
     * Memeriksa apakah dompet merupakan dompet digital.
     */
    public function adalahDompetDigital(): bool
    {
        return $this->jenis_dompet === 'dompet_digital';
    }

    /**
     * Memeriksa apakah dompet masih dapat digunakan.
     */
    public function dapatDigunakan(): bool
    {
        return $this->aktif && $this->deleted_at === null;
    }
}