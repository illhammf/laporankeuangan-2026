<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\KategoriResource\Pages;
use App\Models\Kategori;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

class KategoriResource extends Resource
{
    protected static ?string $model = Kategori::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Manajemen Keuangan';

    protected static ?string $navigationLabel = 'Kategori Transaksi';

    protected static ?string $modelLabel = 'Kategori';

    protected static ?string $pluralModelLabel = 'Kategori Transaksi';

    protected static ?string $recordTitleAttribute = 'nama_kategori';

    protected static ?int $navigationSort = 2;

    /**
     * Form tambah dan edit kategori.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pemilik')
                    ->description(
                        'Tentukan pengguna yang memiliki kategori transaksi ini.'
                    )
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make('pengguna_id')
                            ->label('Pemilik Kategori')
                            ->relationship(
                                name: 'pengguna',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->orderBy('name')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (User $record): string => "{$record->name} — {$record->email}"
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
                                    'kategori_induk_id',
                                    null
                                )
                            )
                            ->helperText(
                                'Kategori hanya dapat digunakan oleh pengguna yang dipilih.'
                            ),
                    ]),

                Forms\Components\Section::make('Informasi Kategori')
                    ->description(
                        'Atur nama, jenis transaksi, dan struktur kategori.'
                    )
                    ->icon('heroicon-o-tag')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\Select::make('jenis_transaksi')
                            ->label('Jenis Transaksi')
                            ->options([
                                'pemasukan' => 'Pemasukan',
                                'pengeluaran' => 'Pengeluaran',
                            ])
                            ->default('pengeluaran')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(
                                fn (Set $set): mixed => $set(
                                    'kategori_induk_id',
                                    null
                                )
                            )
                            ->helperText(
                                'Kategori pemasukan tidak dapat digunakan pada transaksi pengeluaran, dan sebaliknya.'
                            ),

                        Forms\Components\Select::make('kategori_induk_id')
                            ->label('Kategori Induk')
                            ->placeholder('Tidak menggunakan kategori induk')
                            ->options(
                                function (
                                    Get $get,
                                    ?Kategori $record
                                ): array {
                                    $penggunaId = $get('pengguna_id');
                                    $jenisTransaksi = $get('jenis_transaksi');

                                    if (
                                        blank($penggunaId)
                                        || blank($jenisTransaksi)
                                    ) {
                                        return [];
                                    }

                                    return Kategori::query()
                                        ->where(
                                            'pengguna_id',
                                            $penggunaId
                                        )
                                        ->where(
                                            'jenis_transaksi',
                                            $jenisTransaksi
                                        )
                                        ->whereNull('kategori_induk_id')
                                        ->when(
                                            $record,
                                            fn (Builder $query): Builder => $query
                                                ->whereKeyNot(
                                                    $record->getKey()
                                                )
                                        )
                                        ->orderBy('urutan')
                                        ->orderBy('nama_kategori')
                                        ->pluck(
                                            'nama_kategori',
                                            'id'
                                        )
                                        ->all();
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable()
                            ->helperText(
                                'Kosongkan jika kategori ini merupakan kategori utama.'
                            ),

                        Forms\Components\TextInput::make('nama_kategori')
                            ->label('Nama Kategori')
                            ->placeholder(
                                'Contoh: Makanan dan Minuman'
                            )
                            ->required()
                            ->maxLength(100)
                            ->rules([
                                fn (
                                    Get $get,
                                    ?Kategori $record
                                ) => Rule::unique(
                                    table: 'kategori',
                                    column: 'nama_kategori'
                                )
                                    ->where(
                                        fn ($query) => $query
                                            ->where(
                                                'pengguna_id',
                                                $get('pengguna_id')
                                            )
                                            ->where(
                                                'jenis_transaksi',
                                                $get('jenis_transaksi')
                                            )
                                    )
                                    ->ignore($record?->getKey()),
                            ])
                            ->validationMessages([
                                'unique' => 'Nama kategori tersebut sudah digunakan untuk jenis transaksi yang sama.',
                            ]),

                        Forms\Components\TextInput::make('kode_kategori')
                            ->label('Kode Kategori')
                            ->placeholder('Contoh: PG-MAKANAN')
                            ->maxLength(100)
                            ->alphaDash()
                            ->dehydrateStateUsing(
                                fn (?string $state): ?string => filled($state)
                                    ? strtoupper(trim($state))
                                    : null
                            )
                            ->rules([
                                fn (
                                    Get $get,
                                    ?Kategori $record
                                ) => Rule::unique(
                                    table: 'kategori',
                                    column: 'kode_kategori'
                                )
                                    ->where(
                                        fn ($query) => $query->where(
                                            'pengguna_id',
                                            $get('pengguna_id')
                                        )
                                    )
                                    ->ignore($record?->getKey()),
                            ])
                            ->validationMessages([
                                'unique' => 'Kode kategori tersebut sudah digunakan oleh pemilik yang sama.',
                            ])
                            ->helperText(
                                'Kode digunakan untuk identifikasi internal. Gunakan huruf, angka, tanda hubung, atau garis bawah.'
                            ),

                        Forms\Components\Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->placeholder(
                                'Jelaskan penggunaan kategori ini.'
                            )
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Tampilan Kategori')
                    ->description(
                        'Atur ikon, warna, dan urutan kategori pada aplikasi.'
                    )
                    ->icon('heroicon-o-paint-brush')
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('ikon')
                            ->label('Ikon')
                            ->options([
                                'heroicon-o-tag' => 'Tag',
                                'heroicon-o-banknotes' => 'Uang',
                                'heroicon-o-wallet' => 'Dompet',
                                'heroicon-o-briefcase' => 'Pekerjaan',
                                'heroicon-o-building-storefront' => 'Usaha',
                                'heroicon-o-gift' => 'Hadiah',
                                'heroicon-o-shopping-bag' => 'Belanja',
                                'heroicon-o-shopping-cart' => 'Keranjang Belanja',
                                'heroicon-o-cake' => 'Makanan',
                                'heroicon-o-truck' => 'Transportasi',
                                'heroicon-o-home' => 'Rumah',
                                'heroicon-o-bolt' => 'Listrik',
                                'heroicon-o-wifi' => 'Internet',
                                'heroicon-o-device-phone-mobile' => 'Telepon',
                                'heroicon-o-academic-cap' => 'Pendidikan',
                                'heroicon-o-book-open' => 'Buku',
                                'heroicon-o-heart' => 'Kesehatan',
                                'heroicon-o-film' => 'Hiburan',
                                'heroicon-o-map' => 'Perjalanan',
                                'heroicon-o-hand-raised' => 'Sosial',
                                'heroicon-o-chart-bar-square' => 'Investasi',
                                'heroicon-o-receipt-percent' => 'Biaya',
                                'heroicon-o-ellipsis-horizontal-circle' => 'Lainnya',
                            ])
                            ->default('heroicon-o-tag')
                            ->searchable()
                            ->native(false),

                        Forms\Components\ColorPicker::make('warna')
                            ->label('Warna')
                            ->default('#6B7280'),

                        Forms\Components\TextInput::make('urutan')
                            ->label('Urutan Tampilan')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->helperText(
                                'Angka lebih kecil ditampilkan lebih dahulu.'
                            ),
                    ]),

                Forms\Components\Section::make('Pengaturan Kategori')
                    ->description(
                        'Atur sumber dan status penggunaan kategori.'
                    )
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\Toggle::make('kategori_bawaan')
                            ->label('Kategori Bawaan')
                            ->default(false)
                            ->inline(false)
                            ->helperText(
                                'Menandakan kategori disediakan sebagai kategori utama aplikasi.'
                            ),

                        Forms\Components\Toggle::make('aktif')
                            ->label('Kategori Aktif')
                            ->default(true)
                            ->inline(false)
                            ->helperText(
                                'Kategori tidak aktif tidak akan ditampilkan dalam pilihan transaksi baru.'
                            ),
                    ]),
            ]);
    }

    /**
     * Tabel daftar kategori.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pengguna.name')
                    ->label('Pemilik')
                    ->description(
                        fn (Kategori $record): ?string => $record
                            ->pengguna
                            ?->email
                    )
                    ->icon('heroicon-o-user')
                    ->searchable([
                        'name',
                        'email',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('nama_kategori')
                    ->label('Nama Kategori')
                    ->description(
                        function (Kategori $record): string {
                            if ($record->kategoriInduk === null) {
                                return 'Kategori utama';
                            }

                            return 'Subkategori dari '
                                . $record->kategoriInduk->nama_kategori;
                        }
                    )
                    ->icon(
                        fn (Kategori $record): string => $record->ikon
                            ?: 'heroicon-o-tag'
                    )
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'kategoriInduk.nama_kategori'
                )
                    ->label('Kategori Induk')
                    ->placeholder('Tidak ada')
                    ->icon('heroicon-o-arrow-turn-down-right')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('jenis_transaksi')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'pemasukan' => 'Pemasukan',
                            'pengeluaran' => 'Pengeluaran',
                            default => ucfirst($state),
                        }
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'pemasukan' => 'success',
                            'pengeluaran' => 'danger',
                            default => 'gray',
                        }
                    )
                    ->icon(
                        fn (string $state): string => match ($state) {
                            'pemasukan' => 'heroicon-o-arrow-trending-up',
                            'pengeluaran' => 'heroicon-o-arrow-trending-down',
                            default => 'heroicon-o-arrows-up-down',
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('kode_kategori')
                    ->label('Kode')
                    ->placeholder('Tidak ada kode')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Kode kategori berhasil disalin')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('jumlah_transaksi')
                    ->label('Jumlah Transaksi')
                    ->counts('transaksi')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\ColorColumn::make('warna')
                    ->label('Warna')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\IconColumn::make('kategori_bawaan')
                    ->label('Bawaan')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->sortable(),

                Tables\Columns\IconColumn::make('aktif')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

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
                Tables\Filters\SelectFilter::make('pengguna_id')
                    ->label('Pemilik')
                    ->relationship(
                        name: 'pengguna',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('jenis_transaksi')
                    ->label('Jenis Transaksi')
                    ->options([
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('kategori_induk_id')
                    ->label('Kategori Induk')
                    ->relationship(
                        name: 'kategoriInduk',
                        titleAttribute: 'nama_kategori',
                        modifyQueryUsing: fn (Builder $query): Builder => $query
                            ->whereNull('kategori_induk_id')
                            ->orderBy('nama_kategori')
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\TernaryFilter::make('kategori_induk')
                    ->label('Tingkat Kategori')
                    ->placeholder('Semua kategori')
                    ->trueLabel('Kategori utama')
                    ->falseLabel('Subkategori')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereNull('kategori_induk_id'),
                        false: fn (Builder $query): Builder => $query
                            ->whereNotNull('kategori_induk_id'),
                        blank: fn (Builder $query): Builder => $query
                    )
                    ->native(false),

                Tables\Filters\TernaryFilter::make('kategori_bawaan')
                    ->label('Sumber Kategori')
                    ->placeholder('Semua kategori')
                    ->trueLabel('Kategori bawaan')
                    ->falseLabel('Kategori kustom')
                    ->native(false),

                Tables\Filters\TernaryFilter::make('aktif')
                    ->label('Status')
                    ->placeholder('Semua status')
                    ->trueLabel('Hanya aktif')
                    ->falseLabel('Hanya tidak aktif')
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
                    ->before(
                        function (
                            Kategori $record,
                            Tables\Actions\DeleteAction $action
                        ): void {
                            if ($record->subkategori()->exists()) {
                                $action->cancel();

                                \Filament\Notifications\Notification::make()
                                    ->title(
                                        'Kategori tidak dapat dihapus'
                                    )
                                    ->body(
                                        'Kategori ini masih memiliki subkategori. Pindahkan atau hapus subkategori terlebih dahulu.'
                                    )
                                    ->danger()
                                    ->send();

                                return;
                            }

                            if ($record->transaksi()->exists()) {
                                $action->cancel();

                                \Filament\Notifications\Notification::make()
                                    ->title(
                                        'Kategori tidak dapat dihapus'
                                    )
                                    ->body(
                                        'Kategori ini sudah digunakan pada transaksi. Nonaktifkan kategori agar riwayat keuangan tetap aman.'
                                    )
                                    ->danger()
                                    ->send();
                            }
                        }
                    ),

                Tables\Actions\RestoreAction::make()
                    ->label('Pulihkan'),

                Tables\Actions\ForceDeleteAction::make()
                    ->label('Hapus Permanen')
                    ->visible(
                        fn (Kategori $record): bool => ! $record
                            ->transaksi()
                            ->withTrashed()
                            ->exists()
                            && ! $record
                                ->subkategori()
                                ->withTrashed()
                                ->exists()
                    ),
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
            ->defaultSort('urutan')
            ->striped()
            ->emptyStateHeading('Belum ada kategori transaksi')
            ->emptyStateDescription(
                'Tambahkan kategori pemasukan atau pengeluaran untuk mengelompokkan transaksi.'
            )
            ->emptyStateIcon('heroicon-o-tag');
    }

    /**
     * Query dasar resource.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with([
                'pengguna',
                'kategoriInduk',
            ]);
    }

    /**
     * Relasi tambahan kategori.
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Halaman resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKategoris::route('/'),
            'create' => Pages\CreateKategori::route('/create'),
            'edit' => Pages\EditKategori::route('/{record}/edit'),
        ];
    }
}