<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kategori extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Nama tabel yang digunakan model.
     */
    protected $table = 'kategori';

    /**
     * Kolom yang dapat diisi melalui mass assignment.
     */
    protected $fillable = [
        'pengguna_id',
        'kategori_induk_id',
        'nama_kategori',
        'kode_kategori',
        'jenis_transaksi',
        'deskripsi',
        'ikon',
        'warna',
        'urutan',
        'kategori_bawaan',
        'aktif',
    ];

    /**
     * Konversi tipe data atribut.
     */
    protected $casts = [
        'urutan' => 'integer',
        'kategori_bawaan' => 'boolean',
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
     * Pengguna yang memiliki kategori.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'pengguna_id'
        );
    }

    /**
     * Kategori induk dari kategori ini.
     */
    public function kategoriInduk(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'kategori_induk_id'
        );
    }

    /**
     * Daftar subkategori yang berada di bawah kategori ini.
     */
    public function subkategori(): HasMany
    {
        return $this->hasMany(
            self::class,
            'kategori_induk_id'
        )->orderBy('urutan')
            ->orderBy('nama_kategori');
    }

    /**
     * Seluruh transaksi yang menggunakan kategori ini.
     */
    public function transaksi(): HasMany
    {
        return $this->hasMany(
            Transaksi::class,
            'kategori_id'
        );
    }

    /**
     * Seluruh anggaran bulanan untuk kategori ini.
     */
    public function anggaranBulanan(): HasMany
    {
        return $this->hasMany(
            AnggaranBulanan::class,
            'kategori_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scope
    |--------------------------------------------------------------------------
    */

    /**
     * Mengambil kategori yang masih aktif.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    /**
     * Mengambil kategori milik pengguna tertentu.
     */
    public function scopeMilikPengguna(
        Builder $query,
        int $penggunaId
    ): Builder {
        return $query->where('pengguna_id', $penggunaId);
    }

    /**
     * Mengambil kategori pemasukan.
     */
    public function scopePemasukan(Builder $query): Builder
    {
        return $query->where('jenis_transaksi', 'pemasukan');
    }

    /**
     * Mengambil kategori pengeluaran.
     */
    public function scopePengeluaran(Builder $query): Builder
    {
        return $query->where('jenis_transaksi', 'pengeluaran');
    }

    /**
     * Mengambil kategori berdasarkan jenis transaksi.
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
     * Mengambil kategori utama yang tidak memiliki kategori induk.
     */
    public function scopeKategoriUtama(Builder $query): Builder
    {
        return $query->whereNull('kategori_induk_id');
    }

    /**
     * Mengambil kategori yang memiliki kategori induk.
     */
    public function scopeSubkategori(Builder $query): Builder
    {
        return $query->whereNotNull('kategori_induk_id');
    }

    /**
     * Mengambil kategori bawaan sistem.
     */
    public function scopeBawaan(Builder $query): Builder
    {
        return $query->where('kategori_bawaan', true);
    }

    /**
     * Mengambil kategori buatan pengguna.
     */
    public function scopeKustom(Builder $query): Builder
    {
        return $query->where('kategori_bawaan', false);
    }

    /**
     * Mengurutkan kategori berdasarkan urutan dan nama.
     */
    public function scopeTerurut(Builder $query): Builder
    {
        return $query
            ->orderBy('urutan')
            ->orderBy('nama_kategori');
    }

    /**
     * Mencari kategori berdasarkan nama atau kode.
     */
    public function scopeCari(
        Builder $query,
        string $kataKunci
    ): Builder {
        return $query->where(function (Builder $subQuery) use ($kataKunci) {
            $subQuery
                ->where(
                    'nama_kategori',
                    'like',
                    '%' . $kataKunci . '%'
                )
                ->orWhere(
                    'kode_kategori',
                    'like',
                    '%' . $kataKunci . '%'
                );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */

    /**
     * Mendapatkan nama kategori lengkap.
     *
     * Contoh:
     * Makanan dan Minuman > Makan Siang
     */
    public function getNamaLengkapAttribute(): string
    {
        if ($this->kategoriInduk === null) {
            return $this->nama_kategori;
        }

        return $this->kategoriInduk->nama_kategori
            . ' > '
            . $this->nama_kategori;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    /**
     * Memeriksa apakah kategori merupakan kategori pemasukan.
     */
    public function adalahPemasukan(): bool
    {
        return $this->jenis_transaksi === 'pemasukan';
    }

    /**
     * Memeriksa apakah kategori merupakan kategori pengeluaran.
     */
    public function adalahPengeluaran(): bool
    {
        return $this->jenis_transaksi === 'pengeluaran';
    }

    /**
     * Memeriksa apakah kategori merupakan kategori utama.
     */
    public function adalahKategoriUtama(): bool
    {
        return $this->kategori_induk_id === null;
    }

    /**
     * Memeriksa apakah kategori merupakan subkategori.
     */
    public function adalahSubkategori(): bool
    {
        return $this->kategori_induk_id !== null;
    }

    /**
     * Memeriksa apakah kategori memiliki subkategori.
     */
    public function memilikiSubkategori(): bool
    {
        return $this->subkategori()->exists();
    }

    /**
     * Memeriksa apakah kategori masih dapat digunakan.
     */
    public function dapatDigunakan(): bool
    {
        return $this->aktif && $this->deleted_at === null;
    }

    /**
     * Memeriksa kesesuaian kategori dengan jenis transaksi.
     */
    public function sesuaiDenganJenisTransaksi(
        string $jenisTransaksi
    ): bool {
        return $this->jenis_transaksi === $jenisTransaksi;
    }
}