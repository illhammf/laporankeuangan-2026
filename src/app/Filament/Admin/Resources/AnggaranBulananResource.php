<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AnggaranBulananResource\Pages;
use App\Models\AnggaranBulanan;
use App\Models\Kategori;
use App\Models\Transaksi;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class AnggaranBulananResource extends Resource
{
    protected static ?string $model = AnggaranBulanan::class;

    protected static ?string $navigationIcon =
        'heroicon-o-chart-pie';

    protected static ?string $navigationGroup =
        'Manajemen Keuangan';

    protected static ?string $navigationLabel =
        'Anggaran Bulanan';

    protected static ?string $modelLabel =
        'Anggaran Bulanan';

    protected static ?string $pluralModelLabel =
        'Anggaran Bulanan';

    protected static ?int $navigationSort = 5;

    /**
     * Form tambah dan edit anggaran bulanan.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(
                    'Pemilik Anggaran'
                )
                    ->description(
                        'Tentukan pengguna yang memiliki anggaran ini.'
                    )
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make(
                            'pengguna_id'
                        )
                            ->label('Pemilik Anggaran')
                            ->relationship(
                                name: 'pengguna',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (
                                    Builder $query
                                ): Builder => $query
                                    ->orderBy('name')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (User $record): string =>
                                    "{$record->name} — {$record->email}"
                            )
                            ->searchable([
                                'name',
                                'email',
                            ])
                            ->preload()
                            ->native(false)
                            ->default(fn (): ?int => auth()->id())
                            ->required()
                            ->live()
                            ->afterStateUpdated(
                                fn (Set $set): mixed => $set(
                                    'kategori_id',
                                    null
                                )
                            )
                            ->helperText(
                                'Kategori akan disesuaikan dengan pengguna yang dipilih.'
                            ),
                    ]),

                Forms\Components\Section::make(
                    'Kategori dan Periode'
                )
                    ->description(
                        'Pilih kategori pengeluaran serta periode berlakunya anggaran.'
                    )
                    ->icon('heroicon-o-calendar-days')
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        Forms\Components\Select::make(
                            'kategori_id'
                        )
                            ->label('Kategori Pengeluaran')
                            ->placeholder(
                                'Pilih kategori pengeluaran'
                            )
                            ->options(
                                fn (
                                    Get $get,
                                    ?AnggaranBulanan $record
                                ): array => static::
                                    opsiKategoriPengeluaran(
                                        get: $get,
                                        record: $record
                                    )
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->rules([
                                fn (
                                    Get $get
                                ) => function (
                                    string $attribute,
                                    mixed $value,
                                    \Closure $fail
                                ) use ($get): void {
                                    $kategori = Kategori::withTrashed()
                                        ->find($value);

                                    if ($kategori === null) {
                                        $fail(
                                            'Kategori yang dipilih tidak ditemukan.'
                                        );

                                        return;
                                    }

                                    if (
                                        (int) $kategori->pengguna_id
                                        !== (int) $get(
                                            'pengguna_id'
                                        )
                                    ) {
                                        $fail(
                                            'Kategori bukan milik pengguna yang dipilih.'
                                        );

                                        return;
                                    }

                                    if (
                                        $kategori->jenis_transaksi
                                        !== 'pengeluaran'
                                    ) {
                                        $fail(
                                            'Anggaran hanya dapat menggunakan kategori pengeluaran.'
                                        );
                                    }
                                },

                                fn (
                                    Get $get,
                                    ?AnggaranBulanan $record
                                ) => Rule::unique(
                                    table: 'anggaran_bulanan',
                                    column: 'kategori_id'
                                )
                                    ->where(
                                        fn ($query) => $query
                                            ->where(
                                                'pengguna_id',
                                                $get(
                                                    'pengguna_id'
                                                )
                                            )
                                            ->where(
                                                'bulan',
                                                $get('bulan')
                                            )
                                            ->where(
                                                'tahun',
                                                $get('tahun')
                                            )
                                    )
                                    ->ignore(
                                        $record?->getKey()
                                    ),
                            ])
                            ->validationMessages([
                                'unique' =>
                                    'Anggaran untuk kategori dan periode tersebut sudah tersedia.',
                            ])
                            ->helperText(
                                'Satu kategori hanya boleh memiliki satu anggaran dalam periode yang sama.'
                            ),

                        Forms\Components\Select::make('bulan')
                            ->label('Bulan')
                            ->options(
                                AnggaranBulanan::daftarBulan()
                            )
                            ->default(fn (): int => now()->month)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('tahun')
                            ->label('Tahun')
                            ->options(
                                static::daftarTahun()
                            )
                            ->default(fn (): int => now()->year)
                            ->required()
                            ->searchable()
                            ->native(false),
                    ]),

                Forms\Components\Section::make(
                    'Nilai Anggaran'
                )
                    ->description(
                        'Tentukan batas maksimal pengeluaran dan persentase peringatannya.'
                    )
                    ->icon(
                        'heroicon-o-document-currency-dollar'
                    )
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\TextInput::make(
                            'nominal_anggaran'
                        )
                            ->label('Nominal Anggaran')
                            ->prefix('Rp')
                            ->placeholder('0')
                            ->numeric()
                            ->minValue(1)
                            ->step(1000)
                            ->required()
                            ->helperText(
                                'Jumlah maksimal yang direncanakan untuk kategori tersebut.'
                            ),

                        Forms\Components\TextInput::make(
                            'batas_peringatan'
                        )
                            ->label('Batas Peringatan')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(1)
                            ->default(80)
                            ->required()
                            ->helperText(
                                'Sistem memberikan peringatan ketika penggunaan mencapai persentase ini.'
                            ),
                    ]),

                Forms\Components\Section::make(
                    'Ringkasan Realisasi'
                )
                    ->description(
                        'Perhitungan berdasarkan transaksi pengeluaran berstatus selesai.'
                    )
                    ->icon('heroicon-o-presentation-chart-line')
                    ->visible(
                        fn (
                            ?AnggaranBulanan $record
                        ): bool => $record !== null
                    )
                    ->schema([
                        Forms\Components\Placeholder::make(
                            'ringkasan_realisasi'
                        )
                            ->label('')
                            ->content(
                                function (
                                    ?AnggaranBulanan $record
                                ): HtmlString {
                                    if ($record === null) {
                                        return new HtmlString('');
                                    }

                                    $totalTerpakai =
                                        static::hitungTotalTerpakai(
                                            $record
                                        );

                                    $nominalAnggaran =
                                        (float) $record
                                            ->nominal_anggaran;

                                    $sisaAnggaran =
                                        $nominalAnggaran
                                        - $totalTerpakai;

                                    $persentase =
                                        static::hitungPersentase(
                                            totalTerpakai:
                                                $totalTerpakai,
                                            nominalAnggaran:
                                                $nominalAnggaran
                                        );

                                    $kondisi =
                                        static::tentukanKondisi(
                                            persentase:
                                                $persentase,
                                            batasPeringatan:
                                                (float) $record
                                                    ->batas_peringatan
                                        );

                                    return new HtmlString(
                                        '<div style="
                                            display: grid;
                                            grid-template-columns:
                                                repeat(
                                                    auto-fit,
                                                    minmax(180px, 1fr)
                                                );
                                            gap: 16px;
                                        ">
                                            <div>
                                                <div style="
                                                    font-size: 12px;
                                                    color: #6b7280;
                                                ">
                                                    Total Terpakai
                                                </div>
                                                <div style="
                                                    font-size: 18px;
                                                    font-weight: 700;
                                                ">
                                                    '
                                                    . static::formatRupiah(
                                                        $totalTerpakai
                                                    )
                                                    .
                                                '</div>
                                            </div>

                                            <div>
                                                <div style="
                                                    font-size: 12px;
                                                    color: #6b7280;
                                                ">
                                                    Sisa Anggaran
                                                </div>
                                                <div style="
                                                    font-size: 18px;
                                                    font-weight: 700;
                                                ">
                                                    '
                                                    . static::formatRupiah(
                                                        $sisaAnggaran
                                                    )
                                                    .
                                                '</div>
                                            </div>

                                            <div>
                                                <div style="
                                                    font-size: 12px;
                                                    color: #6b7280;
                                                ">
                                                    Penggunaan
                                                </div>
                                                <div style="
                                                    font-size: 18px;
                                                    font-weight: 700;
                                                ">
                                                    '
                                                    . number_format(
                                                        $persentase,
                                                        2,
                                                        ',',
                                                        '.'
                                                    )
                                                    .
                                                    '%
                                                </div>
                                            </div>

                                            <div>
                                                <div style="
                                                    font-size: 12px;
                                                    color: #6b7280;
                                                ">
                                                    Kondisi
                                                </div>
                                                <div style="
                                                    font-size: 18px;
                                                    font-weight: 700;
                                                ">
                                                    '
                                                    . $kondisi
                                                    .
                                                '</div>
                                            </div>
                                        </div>'
                                    );
                                }
                            ),
                    ]),

                Forms\Components\Section::make(
                    'Status dan Pengaturan'
                )
                    ->description(
                        'Atur status penggunaan dan pengulangan anggaran.'
                    )
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Anggaran')
                            ->options(
                                AnggaranBulanan::daftarStatus()
                            )
                            ->default(
                                AnggaranBulanan::STATUS_AKTIF
                            )
                            ->required()
                            ->native(false)
                            ->helperText(
                                'Hanya anggaran aktif yang digunakan dalam pemantauan.'
                            ),

                        Forms\Components\Toggle::make(
                            'ulangi_bulan_berikutnya'
                        )
                            ->label(
                                'Ulangi pada Bulan Berikutnya'
                            )
                            ->default(false)
                            ->inline(false)
                            ->helperText(
                                'Anggaran ini dapat dijadikan acuan untuk periode berikutnya.'
                            ),

                        Forms\Components\Textarea::make(
                            'catatan'
                        )
                            ->label('Catatan')
                            ->placeholder(
                                'Tambahkan informasi atau tujuan anggaran.'
                            )
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tabel daftar anggaran bulanan.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make(
                    'periode_anggaran'
                )
                    ->label('Periode')
                    ->getStateUsing(
                        fn (
                            AnggaranBulanan $record
                        ): string => $record->label_periode
                    )
                    ->icon('heroicon-o-calendar-days')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make(
                    'pengguna.name'
                )
                    ->label('Pemilik')
                    ->description(
                        fn (
                            AnggaranBulanan $record
                        ): ?string => $record
                            ->pengguna
                            ?->email
                    )
                    ->icon('heroicon-o-user')
                    ->searchable([
                        'name',
                        'email',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'kategori.nama_kategori'
                )
                    ->label('Kategori')
                    ->description(
                        function (
                            AnggaranBulanan $record
                        ): ?string {
                            $kategoriInduk = $record
                                ->kategori
                                ?->kategoriInduk
                                ?->nama_kategori;

                            return $kategoriInduk !== null
                                ? "Subkategori dari {$kategoriInduk}"
                                : 'Kategori utama';
                        }
                    )
                    ->icon(
                        fn (
                            AnggaranBulanan $record
                        ): string => $record
                            ->kategori
                            ?->ikon
                            ?: 'heroicon-o-tag'
                    )
                    ->placeholder(
                        'Kategori tidak tersedia'
                    )
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'nominal_anggaran'
                )
                    ->label('Anggaran')
                    ->formatStateUsing(
                        fn ($state): string =>
                            static::formatRupiah(
                                (float) $state
                            )
                    )
                    ->alignEnd()
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'total_realisasi_query'
                )
                    ->label('Terpakai')
                    ->formatStateUsing(
                        fn ($state): string =>
                            static::formatRupiah(
                                (float) $state
                            )
                    )
                    ->color('danger')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'sisa_anggaran_admin'
                )
                    ->label('Sisa')
                    ->getStateUsing(
                        fn (
                            AnggaranBulanan $record
                        ): float =>
                            (float) $record
                                ->nominal_anggaran
                            - (float) (
                                $record
                                    ->total_realisasi_query
                                ?? 0
                            )
                    )
                    ->formatStateUsing(
                        fn ($state): string =>
                            static::formatRupiah(
                                (float) $state
                            )
                    )
                    ->color(
                        fn ($state): string =>
                            (float) $state < 0
                                ? 'danger'
                                : 'success'
                    )
                    ->weight('bold')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make(
                    'persentase_penggunaan_admin'
                )
                    ->label('Penggunaan')
                    ->getStateUsing(
                        function (
                            AnggaranBulanan $record
                        ): float {
                            return static::hitungPersentase(
                                totalTerpakai:
                                    (float) (
                                        $record
                                            ->total_realisasi_query
                                        ?? 0
                                    ),
                                nominalAnggaran:
                                    (float) $record
                                        ->nominal_anggaran
                            );
                        }
                    )
                    ->formatStateUsing(
                        fn ($state): string =>
                            number_format(
                                (float) $state,
                                2,
                                ',',
                                '.'
                            )
                            . '%'
                    )
                    ->badge()
                    ->color(
                        function (
                            $state,
                            AnggaranBulanan $record
                        ): string {
                            $persentase = (float) $state;

                            if ($persentase >= 100) {
                                return 'danger';
                            }

                            if (
                                $persentase
                                >= (float) $record
                                    ->batas_peringatan
                            ) {
                                return 'warning';
                            }

                            return 'success';
                        }
                    ),

                Tables\Columns\TextColumn::make(
                    'kondisi_anggaran'
                )
                    ->label('Kondisi')
                    ->getStateUsing(
                        function (
                            AnggaranBulanan $record
                        ): string {
                            $persentase =
                                static::hitungPersentase(
                                    totalTerpakai:
                                        (float) (
                                            $record
                                                ->total_realisasi_query
                                            ?? 0
                                        ),
                                    nominalAnggaran:
                                        (float) $record
                                            ->nominal_anggaran
                                );

                            return static::tentukanKondisi(
                                persentase: $persentase,
                                batasPeringatan:
                                    (float) $record
                                        ->batas_peringatan
                            );
                        }
                    )
                    ->badge()
                    ->color(
                        fn (
                            string $state
                        ): string => match ($state) {
                            'Aman' => 'success',
                            'Mencapai Batas Peringatan' =>
                                'warning',
                            'Anggaran Habis' => 'danger',
                            'Melebihi Anggaran' => 'danger',
                            default => 'gray',
                        }
                    ),

                Tables\Columns\TextColumn::make(
                    'batas_peringatan'
                )
                    ->label('Batas Peringatan')
                    ->formatStateUsing(
                        fn ($state): string =>
                            number_format(
                                (float) $state,
                                0,
                                ',',
                                '.'
                            )
                            . '%'
                    )
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\IconColumn::make(
                    'ulangi_bulan_berikutnya'
                )
                    ->label('Berulang')
                    ->boolean()
                    ->trueIcon(
                        'heroicon-o-arrow-path-rounded-square'
                    )
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            AnggaranBulanan::daftarStatus()[
                                $state
                            ] ?? ucfirst($state)
                    )
                    ->color(
                        fn (string $state): string =>
                            match ($state) {
                                AnggaranBulanan::STATUS_AKTIF =>
                                    'success',
                                AnggaranBulanan::STATUS_SELESAI =>
                                    'info',
                                AnggaranBulanan::STATUS_DIBATALKAN =>
                                    'danger',
                                default => 'gray',
                            }
                    )
                    ->icon(
                        fn (string $state): string =>
                            match ($state) {
                                AnggaranBulanan::STATUS_AKTIF =>
                                    'heroicon-o-check-circle',
                                AnggaranBulanan::STATUS_SELESAI =>
                                    'heroicon-o-check-badge',
                                AnggaranBulanan::STATUS_DIBATALKAN =>
                                    'heroicon-o-no-symbol',
                                default =>
                                    'heroicon-o-question-mark-circle',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('catatan')
                    ->label('Catatan')
                    ->limit(50)
                    ->placeholder('Tidak ada catatan')
                    ->tooltip(
                        fn (
                            AnggaranBulanan $record
                        ): ?string => $record->catatan
                    )
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Dihapus')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->placeholder('Tidak dihapus')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make(
                    'pengguna_id'
                )
                    ->label('Pemilik')
                    ->relationship(
                        name: 'pengguna',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query
                            ->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make(
                    'kategori_id'
                )
                    ->label('Kategori Pengeluaran')
                    ->relationship(
                        name: 'kategori',
                        titleAttribute: 'nama_kategori',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query
                            ->where(
                                'jenis_transaksi',
                                'pengeluaran'
                            )
                            ->orderBy('nama_kategori')
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('bulan')
                    ->label('Bulan')
                    ->options(
                        AnggaranBulanan::daftarBulan()
                    )
                    ->native(false),

                Tables\Filters\SelectFilter::make('tahun')
                    ->label('Tahun')
                    ->options(
                        static::daftarTahun()
                    )
                    ->searchable()
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Anggaran')
                    ->options(
                        AnggaranBulanan::daftarStatus()
                    )
                    ->native(false),

                Tables\Filters\TernaryFilter::make(
                    'ulangi_bulan_berikutnya'
                )
                    ->label('Pengulangan')
                    ->placeholder('Semua anggaran')
                    ->trueLabel(
                        'Diulangi bulan berikutnya'
                    )
                    ->falseLabel('Tidak diulangi')
                    ->native(false),

                Tables\Filters\TrashedFilter::make()
                    ->label('Data Terhapus')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit'),

                Tables\Actions\DeleteAction::make()
                    ->label('Hapus')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(
                                'Anggaran berhasil dihapus'
                            )
                            ->body(
                                'Data transaksi tidak ikut dihapus.'
                            )
                    ),

                Tables\Actions\RestoreAction::make()
                    ->label('Pulihkan'),

                Tables\Actions\ForceDeleteAction::make()
                    ->label('Hapus Permanen'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Hapus'),

                    Tables\Actions\RestoreBulkAction::make()
                        ->label('Pulihkan'),

                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen'),
                ]),
            ])
            ->defaultSort('tahun', 'desc')
            ->persistFiltersInSession()
            ->striped()
            ->emptyStateHeading(
                'Belum ada anggaran bulanan'
            )
            ->emptyStateDescription(
                'Tambahkan anggaran untuk mengendalikan pengeluaran berdasarkan kategori.'
            )
            ->emptyStateIcon('heroicon-o-chart-pie');
    }

    /**
     * Query resource dengan perhitungan total pengeluaran.
     */
    public static function getEloquentQuery(): Builder
    {
        $totalRealisasi = DB::table('transaksi')
            ->selectRaw(
                'COALESCE(SUM(transaksi.nominal), 0)'
            )
            ->whereColumn(
                'transaksi.pengguna_id',
                'anggaran_bulanan.pengguna_id'
            )
            ->whereColumn(
                'transaksi.kategori_id',
                'anggaran_bulanan.kategori_id'
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
            );

        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->select('anggaran_bulanan.*')
            ->selectSub(
                $totalRealisasi,
                'total_realisasi_query'
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
            ]);
    }

    /**
     * Jumlah anggaran aktif pada bulan berjalan.
     */
    public static function getNavigationBadge(): ?string
    {
        $jumlahAnggaran = AnggaranBulanan::query()
            ->aktif()
            ->bulanIni()
            ->count();

        return $jumlahAnggaran > 0
            ? (string) $jumlahAnggaran
            : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah anggaran aktif bulan ini';
    }

    /**
     * Atribut pencarian global.
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'pengguna.name',
            'pengguna.email',
            'kategori.nama_kategori',
            'catatan',
        ];
    }

    /**
     * Pilihan kategori pengeluaran berdasarkan pengguna.
     */
    protected static function opsiKategoriPengeluaran(
        Get $get,
        ?AnggaranBulanan $record
    ): array {
        $penggunaId = (int) $get('pengguna_id');

        if ($penggunaId <= 0) {
            return [];
        }

        return Kategori::withTrashed()
            ->with([
                'kategoriInduk' => fn (
                    Builder $query
                ): Builder => $query->withTrashed(),
            ])
            ->where('pengguna_id', $penggunaId)
            ->where(
                'jenis_transaksi',
                'pengeluaran'
            )
            ->where(
                function (
                    Builder $query
                ) use ($record): void {
                    $query->where(
                        function (
                            Builder $query
                        ): void {
                            $query
                                ->where('aktif', true)
                                ->whereNull('deleted_at');
                        }
                    );

                    if (filled($record?->kategori_id)) {
                        $query->orWhereKey(
                            $record->kategori_id
                        );
                    }
                }
            )
            ->orderByRaw(
                'kategori_induk_id IS NOT NULL'
            )
            ->orderBy('urutan')
            ->orderBy('nama_kategori')
            ->get()
            ->mapWithKeys(
                function (Kategori $kategori): array {
                    $namaKategori =
                        $kategori->kategoriInduk !== null
                            ? $kategori
                                    ->kategoriInduk
                                    ->nama_kategori
                                . ' > '
                                . $kategori
                                    ->nama_kategori
                            : $kategori->nama_kategori;

                    $status = [];

                    if (! $kategori->aktif) {
                        $status[] = 'Nonaktif';
                    }

                    if ($kategori->trashed()) {
                        $status[] = 'Dihapus';
                    }

                    $labelStatus = empty($status)
                        ? ''
                        : ' — ' . implode(', ', $status);

                    return [
                        $kategori->id =>
                            $namaKategori . $labelStatus,
                    ];
                }
            )
            ->all();
    }

    /**
     * Daftar pilihan tahun.
     */
    protected static function daftarTahun(): array
    {
        $tahunMulai = now()->year - 10;
        $tahunAkhir = now()->year + 5;
        $daftarTahun = [];

        for (
            $tahun = $tahunAkhir;
            $tahun >= $tahunMulai;
            $tahun--
        ) {
            $daftarTahun[$tahun] = (string) $tahun;
        }

        return $daftarTahun;
    }

    /**
     * Menghitung realisasi pengeluaran satu anggaran.
     */
    protected static function hitungTotalTerpakai(
        AnggaranBulanan $anggaran
    ): float {
        return (float) Transaksi::query()
            ->where(
                'pengguna_id',
                $anggaran->pengguna_id
            )
            ->where(
                'kategori_id',
                $anggaran->kategori_id
            )
            ->where(
                'jenis_transaksi',
                Transaksi::JENIS_PENGELUARAN
            )
            ->where(
                'status',
                Transaksi::STATUS_SELESAI
            )
            ->whereMonth(
                'tanggal_transaksi',
                $anggaran->bulan
            )
            ->whereYear(
                'tanggal_transaksi',
                $anggaran->tahun
            )
            ->sum('nominal');
    }

    /**
     * Menghitung persentase penggunaan.
     */
    protected static function hitungPersentase(
        float $totalTerpakai,
        float $nominalAnggaran
    ): float {
        if ($nominalAnggaran <= 0) {
            return 0;
        }

        return round(
            ($totalTerpakai / $nominalAnggaran)
            * 100,
            2
        );
    }

    /**
     * Menentukan kondisi penggunaan anggaran.
     */
    protected static function tentukanKondisi(
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
     * Format nominal Rupiah.
     */
    protected static function formatRupiah(
        float $nominal
    ): string {
        $awalan = $nominal < 0
            ? '-Rp'
            : 'Rp';

        return $awalan . number_format(
            abs($nominal),
            0,
            ',',
            '.'
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                Pages\ListAnggaranBulanans::route('/'),

            'create' =>
                Pages\CreateAnggaranBulanan::route(
                    '/create'
                ),

            'edit' =>
                Pages\EditAnggaranBulanan::route(
                    '/{record}/edit'
                ),
        ];
    }
}