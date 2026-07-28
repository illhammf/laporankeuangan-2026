<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TransaksiResource\Pages;
use App\Models\Dompet;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TransaksiResource extends Resource
{
    protected static ?string $model = Transaksi::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Manajemen Keuangan';

    protected static ?string $navigationLabel = 'Transaksi';

    protected static ?string $modelLabel = 'Transaksi';

    protected static ?string $pluralModelLabel = 'Transaksi';

    protected static ?string $recordTitleAttribute = 'nama_transaksi';

    protected static ?int $navigationSort = 3;

    /**
     * Form tambah dan edit transaksi.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pemilik dan Jenis Transaksi')
                    ->description(
                        'Tentukan pemilik serta jenis transaksi yang akan dicatat.'
                    )
                    ->icon('heroicon-o-user')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\Select::make('pengguna_id')
                            ->label('Pemilik Transaksi')
                            ->relationship(
                                name: 'pengguna',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (
                                    Builder $query
                                ): Builder => $query->orderBy('name')
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
                                function (Set $set): void {
                                    $set('dompet_id', null);
                                    $set('kategori_id', null);
                                }
                            )
                            ->helperText(
                                'Dompet dan kategori akan disesuaikan dengan pemilik yang dipilih.'
                            ),

                        Forms\Components\Select::make('jenis_transaksi')
                            ->label('Jenis Transaksi')
                            ->options([
                                Transaksi::JENIS_PEMASUKAN => 'Pemasukan',
                                Transaksi::JENIS_PENGELUARAN => 'Pengeluaran',
                            ])
                            ->default(
                                Transaksi::JENIS_PENGELUARAN
                            )
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(
                                fn (Set $set): mixed => $set(
                                    'kategori_id',
                                    null
                                )
                            )
                            ->helperText(
                                'Jenis transaksi menentukan kategori yang dapat dipilih.'
                            ),
                    ]),

                Forms\Components\Section::make('Informasi Utama Transaksi')
                    ->description(
                        'Isi tanggal, nama, nominal, dompet, dan kategori transaksi.'
                    )
                    ->icon('heroicon-o-document-currency-dollar')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('kode_transaksi')
                            ->label('Kode Transaksi')
                            ->default(
                                fn (): string =>
                                    static::buatKodeTransaksi()
                            )
                            ->required()
                            ->maxLength(50)
                            ->alphaDash()
                            ->dehydrateStateUsing(
                                fn (?string $state): ?string =>
                                    filled($state)
                                        ? strtoupper(trim($state))
                                        : null
                            )
                            ->rules([
                                fn (
                                    ?Transaksi $record
                                ) => Rule::unique(
                                    table: 'transaksi',
                                    column: 'kode_transaksi'
                                )->ignore($record?->getKey()),
                            ])
                            ->validationMessages([
                                'unique' =>
                                    'Kode transaksi tersebut sudah digunakan.',
                            ])
                            ->helperText(
                                'Kode dibuat otomatis, tetapi tetap dapat diubah oleh admin.'
                            ),

                        Forms\Components\DateTimePicker::make(
                            'tanggal_transaksi'
                        )
                            ->label('Tanggal dan Waktu')
                            ->default(fn () => now())
                            ->timezone('Asia/Jakarta')
                            ->displayFormat('d M Y H:i')
                            ->seconds(false)
                            ->native(false)
                            ->required(),

                        Forms\Components\Select::make('dompet_id')
                            ->label('Dompet atau Rekening')
                            ->placeholder(
                                'Pilih dompet yang digunakan'
                            )
                            ->options(
                                function (
                                    Get $get,
                                    ?Transaksi $record
                                ): array {
                                    $penggunaId = $get('pengguna_id');

                                    if (blank($penggunaId)) {
                                        return [];
                                    }

                                    return Dompet::withTrashed()
                                        ->where(
                                            'pengguna_id',
                                            $penggunaId
                                        )
                                        ->where(
                                            function (
                                                Builder $query
                                            ) use ($record): void {
                                                $query->where(
                                                    'aktif',
                                                    true
                                                );

                                                if (
                                                    filled(
                                                        $record?->dompet_id
                                                    )
                                                ) {
                                                    $query->orWhereKey(
                                                        $record->dompet_id
                                                    );
                                                }
                                            }
                                        )
                                        ->orderBy('urutan')
                                        ->orderBy('nama_dompet')
                                        ->get()
                                        ->mapWithKeys(
                                            function (
                                                Dompet $dompet
                                            ): array {
                                                $status = [];

                                                if (! $dompet->aktif) {
                                                    $status[] = 'Nonaktif';
                                                }

                                                if ($dompet->trashed()) {
                                                    $status[] = 'Dihapus';
                                                }

                                                $labelStatus = empty($status)
                                                    ? ''
                                                    : ' — '
                                                        . implode(
                                                            ', ',
                                                            $status
                                                        );

                                                return [
                                                    $dompet->id =>
                                                        $dompet
                                                            ->nama_dompet
                                                        . $labelStatus,
                                                ];
                                            }
                                        )
                                        ->all();
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->rules([
                                fn (Get $get) => Rule::exists(
                                    table: 'dompet',
                                    column: 'id'
                                )->where(
                                    fn ($query) => $query->where(
                                        'pengguna_id',
                                        $get('pengguna_id')
                                    )
                                ),
                            ])
                            ->validationMessages([
                                'exists' =>
                                    'Dompet tidak ditemukan atau bukan milik pengguna yang dipilih.',
                            ])
                            ->helperText(
                                'Saldo dompet akan berubah jika transaksi berstatus selesai.'
                            ),

                        Forms\Components\Select::make('kategori_id')
                            ->label('Kategori')
                            ->placeholder(
                                'Pilih kategori transaksi'
                            )
                            ->options(
                                function (
                                    Get $get,
                                    ?Transaksi $record
                                ): array {
                                    $penggunaId = $get('pengguna_id');
                                    $jenisTransaksi = $get(
                                        'jenis_transaksi'
                                    );

                                    if (
                                        blank($penggunaId)
                                        || blank($jenisTransaksi)
                                    ) {
                                        return [];
                                    }

                                    return Kategori::withTrashed()
                                        ->with('kategoriInduk')
                                        ->where(
                                            'pengguna_id',
                                            $penggunaId
                                        )
                                        ->where(
                                            'jenis_transaksi',
                                            $jenisTransaksi
                                        )
                                        ->where(
                                            function (
                                                Builder $query
                                            ) use ($record): void {
                                                $query->where(
                                                    'aktif',
                                                    true
                                                );

                                                if (
                                                    filled(
                                                        $record
                                                            ?->kategori_id
                                                    )
                                                ) {
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
                                            function (
                                                Kategori $kategori
                                            ): array {
                                                $nama = $kategori
                                                    ->kategoriInduk
                                                    ? $kategori
                                                            ->kategoriInduk
                                                            ->nama_kategori
                                                        . ' > '
                                                        . $kategori
                                                            ->nama_kategori
                                                    : $kategori
                                                        ->nama_kategori;

                                                $status = [];

                                                if (! $kategori->aktif) {
                                                    $status[] = 'Nonaktif';
                                                }

                                                if (
                                                    $kategori->trashed()
                                                ) {
                                                    $status[] = 'Dihapus';
                                                }

                                                $labelStatus = empty(
                                                    $status
                                                )
                                                    ? ''
                                                    : ' — '
                                                        . implode(
                                                            ', ',
                                                            $status
                                                        );

                                                return [
                                                    $kategori->id =>
                                                        $nama
                                                        . $labelStatus,
                                                ];
                                            }
                                        )
                                        ->all();
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->rules([
                                fn (Get $get) => Rule::exists(
                                    table: 'kategori',
                                    column: 'id'
                                )->where(
                                    fn ($query) => $query
                                        ->where(
                                            'pengguna_id',
                                            $get('pengguna_id')
                                        )
                                        ->where(
                                            'jenis_transaksi',
                                            $get(
                                                'jenis_transaksi'
                                            )
                                        )
                                ),
                            ])
                            ->validationMessages([
                                'exists' =>
                                    'Kategori tidak sesuai dengan pengguna atau jenis transaksi yang dipilih.',
                            ]),

                        Forms\Components\TextInput::make(
                            'nama_transaksi'
                        )
                            ->label('Nama Transaksi')
                            ->placeholder(
                                'Contoh: Beli makan siang'
                            )
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('nominal')
                            ->label('Nominal')
                            ->prefix('Rp')
                            ->placeholder('0')
                            ->numeric()
                            ->minValue(1)
                            ->step(1000)
                            ->required()
                            ->helperText(
                                'Masukkan nominal transaksi tanpa tanda titik atau koma.'
                            ),

                        Forms\Components\TextInput::make(
                            'pihak_terkait'
                        )
                            ->label('Pihak Terkait')
                            ->placeholder(
                                'Contoh: Indomaret, perusahaan, atau pemberi dana'
                            )
                            ->maxLength(150),

                        Forms\Components\TextInput::make('lokasi')
                            ->label('Lokasi')
                            ->placeholder(
                                'Contoh: Kampus, rumah, atau nama tempat'
                            )
                            ->maxLength(255),

                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->placeholder(
                                'Tambahkan informasi lain mengenai transaksi.'
                            )
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Bukti Transaksi')
                    ->description(
                        'Unggah foto struk, kuitansi, atau dokumen pendukung.'
                    )
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        Forms\Components\FileUpload::make(
                            'bukti_transaksi'
                        )
                            ->label('File Bukti Transaksi')
                            ->disk('public')
                            ->directory('bukti-transaksi')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'application/pdf',
                            ])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            ->helperText(
                                'Format yang diperbolehkan: JPG, PNG, WEBP, atau PDF. Ukuran maksimal 5 MB.'
                            )
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(
                    'Status dan Pengaturan Pencatatan'
                )
                    ->description(
                        'Atur status transaksi dan sumber pencatatannya.'
                    )
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Transaksi')
                            ->options(
                                Transaksi::daftarStatus()
                            )
                            ->default(
                                Transaksi::STATUS_SELESAI
                            )
                            ->required()
                            ->native(false)
                            ->helperText(
                                'Hanya transaksi selesai yang memengaruhi saldo dan laporan.'
                            ),

                        Forms\Components\Select::make(
                            'sumber_pencatatan'
                        )
                            ->label('Sumber Pencatatan')
                            ->options(
                                Transaksi::daftarSumberPencatatan()
                            )
                            ->default(
                                Transaksi::SUMBER_MANUAL
                            )
                            ->required()
                            ->native(false),

                        Forms\Components\Toggle::make(
                            'transaksi_rutin'
                        )
                            ->label('Transaksi Rutin')
                            ->default(false)
                            ->inline(false)
                            ->helperText(
                                'Aktifkan untuk menandai transaksi yang biasanya terjadi berulang.'
                            ),
                    ]),
            ]);
    }

    /**
     * Tabel daftar transaksi.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make(
                    'tanggal_transaksi'
                )
                    ->label('Tanggal')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->sortable()
                    ->icon('heroicon-o-calendar-days'),

                Tables\Columns\TextColumn::make(
                    'nama_transaksi'
                )
                    ->label('Transaksi')
                    ->description(
                        fn (Transaksi $record): string =>
                            $record->kode_transaksi
                    )
                    ->weight('bold')
                    ->searchable([
                        'nama_transaksi',
                        'kode_transaksi',
                        'pihak_terkait',
                        'lokasi',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('pengguna.name')
                    ->label('Pemilik')
                    ->description(
                        fn (Transaksi $record): ?string =>
                            $record->pengguna?->email
                    )
                    ->icon('heroicon-o-user')
                    ->searchable([
                        'name',
                        'email',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'dompet.nama_dompet'
                )
                    ->label('Dompet')
                    ->icon(
                        fn (Transaksi $record): string =>
                            $record->dompet?->ikon
                                ?: 'heroicon-o-wallet'
                    )
                    ->placeholder('Dompet tidak tersedia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'kategori.nama_kategori'
                )
                    ->label('Kategori')
                    ->description(
                        fn (Transaksi $record): ?string =>
                            $record->kategori
                                ?->kategoriInduk
                                ?->nama_kategori
                    )
                    ->icon(
                        fn (Transaksi $record): string =>
                            $record->kategori?->ikon
                                ?: 'heroicon-o-tag'
                    )
                    ->placeholder('Kategori tidak tersedia')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'jenis_transaksi'
                )
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            match ($state) {
                                Transaksi::JENIS_PEMASUKAN =>
                                    'Pemasukan',
                                Transaksi::JENIS_PENGELUARAN =>
                                    'Pengeluaran',
                                default => ucfirst($state),
                            }
                    )
                    ->color(
                        fn (string $state): string =>
                            match ($state) {
                                Transaksi::JENIS_PEMASUKAN =>
                                    'success',
                                Transaksi::JENIS_PENGELUARAN =>
                                    'danger',
                                default => 'gray',
                            }
                    )
                    ->icon(
                        fn (string $state): string =>
                            match ($state) {
                                Transaksi::JENIS_PEMASUKAN =>
                                    'heroicon-o-arrow-trending-up',
                                Transaksi::JENIS_PENGELUARAN =>
                                    'heroicon-o-arrow-trending-down',
                                default =>
                                    'heroicon-o-arrows-up-down',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('nominal')
                    ->label('Nominal')
                    ->formatStateUsing(
                        function (
                            $state,
                            Transaksi $record
                        ): string {
                            $tanda = $record->adalahPemasukan()
                                ? '+ '
                                : '- ';

                            return $tanda
                                . 'Rp'
                                . number_format(
                                    (float) $state,
                                    0,
                                    ',',
                                    '.'
                                );
                        }
                    )
                    ->color(
                        fn (
                            Transaksi $record
                        ): string => $record->adalahPemasukan()
                            ? 'success'
                            : 'danger'
                    )
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            Transaksi::daftarStatus()[$state]
                                ?? ucfirst($state)
                    )
                    ->color(
                        fn (string $state): string =>
                            match ($state) {
                                Transaksi::STATUS_SELESAI =>
                                    'success',
                                Transaksi::STATUS_TERTUNDA =>
                                    'warning',
                                Transaksi::STATUS_DIBATALKAN =>
                                    'danger',
                                default => 'gray',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'pihak_terkait'
                )
                    ->label('Pihak Terkait')
                    ->placeholder('Tidak dicantumkan')
                    ->searchable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make('lokasi')
                    ->label('Lokasi')
                    ->placeholder('Tidak dicantumkan')
                    ->searchable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make(
                    'bukti_transaksi'
                )
                    ->label('Bukti')
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            filled($state)
                                ? 'Tersedia'
                                : 'Tidak ada'
                    )
                    ->badge()
                    ->color(
                        fn (?string $state): string =>
                            filled($state)
                                ? 'success'
                                : 'gray'
                    )
                    ->icon(
                        fn (?string $state): string =>
                            filled($state)
                                ? 'heroicon-o-paper-clip'
                                : 'heroicon-o-minus-circle'
                    )
                    ->url(
                        fn (
                            Transaksi $record
                        ): ?string => filled(
                            $record->bukti_transaksi
                        )
                            ? Storage::disk('public')->url(
                                $record->bukti_transaksi
                            )
                            : null
                    )
                    ->openUrlInNewTab()
                    ->toggleable(),

                Tables\Columns\IconColumn::make(
                    'transaksi_rutin'
                )
                    ->label('Rutin')
                    ->boolean()
                    ->trueIcon(
                        'heroicon-o-arrow-path-rounded-square'
                    )
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make(
                    'sumber_pencatatan'
                )
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            Transaksi::daftarSumberPencatatan()[
                                $state
                            ] ?? ucfirst($state)
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
                Tables\Filters\SelectFilter::make('pengguna_id')
                    ->label('Pemilik')
                    ->relationship(
                        name: 'pengguna',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('dompet_id')
                    ->label('Dompet')
                    ->relationship(
                        name: 'dompet',
                        titleAttribute: 'nama_dompet',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query
                            ->orderBy('urutan')
                            ->orderBy('nama_dompet')
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship(
                        name: 'kategori',
                        titleAttribute: 'nama_kategori',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query
                            ->orderBy('nama_kategori')
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make(
                    'jenis_transaksi'
                )
                    ->label('Jenis Transaksi')
                    ->options(
                        Transaksi::daftarJenisTransaksi()
                    )
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(
                        Transaksi::daftarStatus()
                    )
                    ->native(false),

                Tables\Filters\SelectFilter::make(
                    'sumber_pencatatan'
                )
                    ->label('Sumber Pencatatan')
                    ->options(
                        Transaksi::daftarSumberPencatatan()
                    )
                    ->native(false),

                Tables\Filters\TernaryFilter::make(
                    'transaksi_rutin'
                )
                    ->label('Transaksi Rutin')
                    ->placeholder('Semua transaksi')
                    ->trueLabel('Hanya transaksi rutin')
                    ->falseLabel('Hanya transaksi tidak rutin')
                    ->native(false),

                Tables\Filters\Filter::make('periode')
                    ->label('Periode Transaksi')
                    ->form([
                        Forms\Components\DatePicker::make(
                            'tanggal_mulai'
                        )
                            ->label('Tanggal Mulai')
                            ->native(false),

                        Forms\Components\DatePicker::make(
                            'tanggal_selesai'
                        )
                            ->label('Tanggal Selesai')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(
                        fn (
                            Builder $query,
                            array $data
                        ): Builder => $query
                            ->when(
                                $data['tanggal_mulai'] ?? null,
                                fn (
                                    Builder $query,
                                    $tanggal
                                ): Builder => $query->whereDate(
                                    'tanggal_transaksi',
                                    '>=',
                                    $tanggal
                                )
                            )
                            ->when(
                                $data['tanggal_selesai'] ?? null,
                                fn (
                                    Builder $query,
                                    $tanggal
                                ): Builder => $query->whereDate(
                                    'tanggal_transaksi',
                                    '<=',
                                    $tanggal
                                )
                            )
                    ),

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
                                'Transaksi berhasil dihapus'
                            )
                            ->body(
                                'Saldo dan laporan akan dihitung kembali secara otomatis.'
                            )
                    ),

                Tables\Actions\RestoreAction::make()
                    ->label('Pulihkan')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(
                                'Transaksi berhasil dipulihkan'
                            )
                            ->body(
                                'Saldo dan laporan akan dihitung kembali secara otomatis.'
                            )
                    ),

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
            ->defaultSort(
                'tanggal_transaksi',
                'desc'
            )
            ->persistFiltersInSession()
            ->striped()
            ->emptyStateHeading('Belum ada transaksi')
            ->emptyStateDescription(
                'Tambahkan pemasukan atau pengeluaran untuk mulai menyusun laporan keuangan.'
            )
            ->emptyStateIcon(
                'heroicon-o-arrows-right-left'
            );
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
                'dompet',
                'kategori.kategoriInduk',
            ]);
    }

    /**
     * Membuat kode transaksi unik.
     *
     * Contoh:
     * TRX-20260728-203015-A1B2
     */
    protected static function buatKodeTransaksi(): string
    {
        do {
            $kode = 'TRX-'
                . now()->format('Ymd-His')
                . '-'
                . Str::upper(Str::random(4));
        } while (
            Transaksi::withTrashed()
                ->where('kode_transaksi', $kode)
                ->exists()
        );

        return $kode;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransaksis::route('/'),
            'create' => Pages\CreateTransaksi::route('/create'),
            'edit' => Pages\EditTransaksi::route(
                '/{record}/edit'
            ),
        ];
    }
}