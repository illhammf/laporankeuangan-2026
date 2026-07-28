<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TransferDompetResource\Pages;
use App\Models\Dompet;
use App\Models\TransferDompet;
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

class TransferDompetResource extends Resource
{
    protected static ?string $model = TransferDompet::class;

    protected static ?string $navigationIcon =
        'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup =
        'Manajemen Keuangan';

    protected static ?string $navigationLabel =
        'Transfer Dompet';

    protected static ?string $modelLabel =
        'Transfer Dompet';

    protected static ?string $pluralModelLabel =
        'Transfer Antar-Dompet';

    protected static ?string $recordTitleAttribute =
        'kode_transfer';

    protected static ?int $navigationSort = 4;

    /**
     * Form tambah dan edit transfer antar-dompet.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(
                    'Pemilik Transfer'
                )
                    ->description(
                        'Pilih pengguna yang melakukan transfer antar-dompet.'
                    )
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make(
                            'pengguna_id'
                        )
                            ->label('Pemilik Transfer')
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
                                function (Set $set): void {
                                    $set('dompet_asal_id', null);
                                    $set('dompet_tujuan_id', null);
                                }
                            )
                            ->helperText(
                                'Dompet asal dan tujuan akan disesuaikan dengan pengguna yang dipilih.'
                            ),
                    ]),

                Forms\Components\Section::make(
                    'Informasi Transfer'
                )
                    ->description(
                        'Atur dompet asal, dompet tujuan, tanggal, dan nominal transfer.'
                    )
                    ->icon('heroicon-o-arrows-right-left')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\TextInput::make(
                            'kode_transfer'
                        )
                            ->label('Kode Transfer')
                            ->default(
                                fn (): string =>
                                    static::buatKodeTransfer()
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
                                    ?TransferDompet $record
                                ) => Rule::unique(
                                    table: 'transfer_dompet',
                                    column: 'kode_transfer'
                                )->ignore($record?->getKey()),
                            ])
                            ->validationMessages([
                                'unique' =>
                                    'Kode transfer tersebut sudah digunakan.',
                            ])
                            ->helperText(
                                'Kode dibuat otomatis, tetapi tetap dapat diubah oleh admin.'
                            ),

                        Forms\Components\DateTimePicker::make(
                            'tanggal_transfer'
                        )
                            ->label('Tanggal dan Waktu Transfer')
                            ->default(fn () => now())
                            ->timezone('Asia/Jakarta')
                            ->displayFormat('d M Y H:i')
                            ->seconds(false)
                            ->native(false)
                            ->required(),

                        Forms\Components\Select::make(
                            'dompet_asal_id'
                        )
                            ->label('Dompet Asal')
                            ->placeholder(
                                'Pilih dompet yang mengirim dana'
                            )
                            ->options(
                                fn (
                                    Get $get,
                                    ?TransferDompet $record
                                ): array => static::opsiDompet(
                                    get: $get,
                                    record: $record,
                                    kecualiId: filled(
                                        $get('dompet_tujuan_id')
                                    )
                                        ? (int) $get(
                                            'dompet_tujuan_id'
                                        )
                                        : null
                                )
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    Get $get,
                                    Set $set,
                                    $state
                                ): void {
                                    if (
                                        filled($state)
                                        && (int) $state ===
                                            (int) $get(
                                                'dompet_tujuan_id'
                                            )
                                    ) {
                                        $set(
                                            'dompet_tujuan_id',
                                            null
                                        );
                                    }
                                }
                            )
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

                                fn (Get $get) => function (
                                    string $attribute,
                                    mixed $value,
                                    \Closure $fail
                                ) use ($get): void {
                                    if (
                                        filled($value)
                                        && (int) $value ===
                                            (int) $get(
                                                'dompet_tujuan_id'
                                            )
                                    ) {
                                        $fail(
                                            'Dompet asal dan tujuan harus berbeda.'
                                        );
                                    }
                                },
                            ])
                            ->validationMessages([
                                'exists' =>
                                    'Dompet asal tidak ditemukan atau bukan milik pengguna yang dipilih.',
                            ])
                            ->helperText(
                                'Saldo dompet asal akan dikurangi sebesar nominal transfer dan biaya administrasi.'
                            ),

                        Forms\Components\Select::make(
                            'dompet_tujuan_id'
                        )
                            ->label('Dompet Tujuan')
                            ->placeholder(
                                'Pilih dompet yang menerima dana'
                            )
                            ->options(
                                fn (
                                    Get $get,
                                    ?TransferDompet $record
                                ): array => static::opsiDompet(
                                    get: $get,
                                    record: $record,
                                    kecualiId: filled(
                                        $get('dompet_asal_id')
                                    )
                                        ? (int) $get(
                                            'dompet_asal_id'
                                        )
                                        : null
                                )
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
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

                                fn (Get $get) => function (
                                    string $attribute,
                                    mixed $value,
                                    \Closure $fail
                                ) use ($get): void {
                                    if (
                                        filled($value)
                                        && (int) $value ===
                                            (int) $get(
                                                'dompet_asal_id'
                                            )
                                    ) {
                                        $fail(
                                            'Dompet tujuan harus berbeda dari dompet asal.'
                                        );
                                    }
                                },
                            ])
                            ->validationMessages([
                                'exists' =>
                                    'Dompet tujuan tidak ditemukan atau bukan milik pengguna yang dipilih.',
                            ])
                            ->helperText(
                                'Dompet tujuan akan menerima dana sesuai nominal transfer.'
                            ),

                        Forms\Components\TextInput::make(
                            'nominal'
                        )
                            ->label('Nominal Transfer')
                            ->prefix('Rp')
                            ->placeholder('0')
                            ->numeric()
                            ->minValue(1)
                            ->step(1000)
                            ->required()
                            ->helperText(
                                'Nominal yang akan diterima oleh dompet tujuan.'
                            ),

                        Forms\Components\TextInput::make(
                            'biaya_admin'
                        )
                            ->label('Biaya Administrasi')
                            ->prefix('Rp')
                            ->placeholder('0')
                            ->numeric()
                            ->minValue(0)
                            ->step(1000)
                            ->default(0)
                            ->required()
                            ->helperText(
                                'Biaya administrasi hanya mengurangi saldo dompet asal.'
                            ),

                        Forms\Components\Textarea::make(
                            'catatan'
                        )
                            ->label('Catatan Transfer')
                            ->placeholder(
                                'Contoh: Top up DANA dari rekening BRI.'
                            )
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(
                    'Bukti Transfer'
                )
                    ->description(
                        'Unggah bukti transfer atau dokumen pendukung.'
                    )
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->schema([
                        Forms\Components\FileUpload::make(
                            'bukti_transfer'
                        )
                            ->label('File Bukti Transfer')
                            ->disk('public')
                            ->directory('bukti-transfer')
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
                            ->helperText(
                                'Format: JPG, PNG, WEBP, atau PDF. Ukuran maksimal 5 MB.'
                            )
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(
                    'Status dan Penyelesaian'
                )
                    ->description(
                        'Hanya transfer berstatus selesai yang memengaruhi saldo dompet.'
                    )
                    ->icon('heroicon-o-check-circle')
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Transfer')
                            ->options(
                                TransferDompet::daftarStatus()
                            )
                            ->default(
                                TransferDompet::STATUS_SELESAI
                            )
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    Get $get,
                                    Set $set,
                                    ?string $state
                                ): void {
                                    if (
                                        $state ===
                                            TransferDompet::STATUS_SELESAI
                                    ) {
                                        if (
                                            blank(
                                                $get(
                                                    'diselesaikan_pada'
                                                )
                                            )
                                        ) {
                                            $set(
                                                'diselesaikan_pada',
                                                now()
                                            );
                                        }

                                        return;
                                    }

                                    $set(
                                        'diselesaikan_pada',
                                        null
                                    );
                                }
                            )
                            ->helperText(
                                'Transfer tertunda, gagal, atau dibatalkan tidak mengubah saldo.'
                            ),

                        Forms\Components\Select::make(
                            'sumber_pencatatan'
                        )
                            ->label('Sumber Pencatatan')
                            ->options(
                                TransferDompet::
                                    daftarSumberPencatatan()
                            )
                            ->default(
                                TransferDompet::SUMBER_MANUAL
                            )
                            ->required()
                            ->native(false),

                        Forms\Components\DateTimePicker::make(
                            'diselesaikan_pada'
                        )
                            ->label('Waktu Penyelesaian')
                            ->default(fn () => now())
                            ->timezone('Asia/Jakarta')
                            ->displayFormat('d M Y H:i')
                            ->seconds(false)
                            ->native(false)
                            ->required(
                                fn (Get $get): bool =>
                                    $get('status') ===
                                    TransferDompet::STATUS_SELESAI
                            )
                            ->disabled(
                                fn (Get $get): bool =>
                                    $get('status') !==
                                    TransferDompet::STATUS_SELESAI
                            )
                            ->dehydrated()
                            ->helperText(
                                'Diisi ketika transfer benar-benar selesai.'
                            ),
                    ]),
            ]);
    }

    /**
     * Tabel daftar transfer antar-dompet.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make(
                    'tanggal_transfer'
                )
                    ->label('Tanggal')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->icon('heroicon-o-calendar-days')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'kode_transfer'
                )
                    ->label('Kode Transfer')
                    ->description(
                        fn (
                            TransferDompet $record
                        ): ?string => $record->catatan
                    )
                    ->searchable()
                    ->copyable()
                    ->copyMessage(
                        'Kode transfer berhasil disalin'
                    )
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'pengguna.name'
                )
                    ->label('Pemilik')
                    ->description(
                        fn (
                            TransferDompet $record
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
                    'dompetAsal.nama_dompet'
                )
                    ->label('Dompet Asal')
                    ->description(
                        fn (
                            TransferDompet $record
                        ): string => static::labelJenisDompet(
                            $record->dompetAsal
                                ?->jenis_dompet
                        )
                    )
                    ->icon(
                        fn (
                            TransferDompet $record
                        ): string => $record
                            ->dompetAsal
                            ?->ikon
                            ?: 'heroicon-o-wallet'
                    )
                    ->placeholder(
                        'Dompet tidak tersedia'
                    )
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'dompetTujuan.nama_dompet'
                )
                    ->label('Dompet Tujuan')
                    ->description(
                        fn (
                            TransferDompet $record
                        ): string => static::labelJenisDompet(
                            $record->dompetTujuan
                                ?->jenis_dompet
                        )
                    )
                    ->icon(
                        fn (
                            TransferDompet $record
                        ): string => $record
                            ->dompetTujuan
                            ?->ikon
                            ?: 'heroicon-o-wallet'
                    )
                    ->placeholder(
                        'Dompet tidak tersedia'
                    )
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nominal')
                    ->label('Nominal Transfer')
                    ->formatStateUsing(
                        fn ($state): string =>
                            'Rp'
                            . number_format(
                                (float) $state,
                                0,
                                ',',
                                '.'
                            )
                    )
                    ->color('info')
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'biaya_admin'
                )
                    ->label('Biaya Admin')
                    ->formatStateUsing(
                        fn ($state): string =>
                            'Rp'
                            . number_format(
                                (float) $state,
                                0,
                                ',',
                                '.'
                            )
                    )
                    ->color(
                        fn ($state): string =>
                            (float) $state > 0
                                ? 'warning'
                                : 'gray'
                    )
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'total_potongan'
                )
                    ->label('Total dari Dompet Asal')
                    ->getStateUsing(
                        fn (
                            TransferDompet $record
                        ): float =>
                            (float) $record->nominal
                            + (float) $record->biaya_admin
                    )
                    ->formatStateUsing(
                        fn ($state): string =>
                            'Rp'
                            . number_format(
                                (float) $state,
                                0,
                                ',',
                                '.'
                            )
                    )
                    ->color('danger')
                    ->weight('bold')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            TransferDompet::daftarStatus()[
                                $state
                            ] ?? ucfirst($state)
                    )
                    ->color(
                        fn (string $state): string =>
                            match ($state) {
                                TransferDompet::STATUS_SELESAI =>
                                    'success',
                                TransferDompet::STATUS_TERTUNDA =>
                                    'warning',
                                TransferDompet::STATUS_GAGAL =>
                                    'danger',
                                TransferDompet::STATUS_DIBATALKAN =>
                                    'gray',
                                default => 'gray',
                            }
                    )
                    ->icon(
                        fn (string $state): string =>
                            match ($state) {
                                TransferDompet::STATUS_SELESAI =>
                                    'heroicon-o-check-circle',
                                TransferDompet::STATUS_TERTUNDA =>
                                    'heroicon-o-clock',
                                TransferDompet::STATUS_GAGAL =>
                                    'heroicon-o-x-circle',
                                TransferDompet::STATUS_DIBATALKAN =>
                                    'heroicon-o-no-symbol',
                                default =>
                                    'heroicon-o-question-mark-circle',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'bukti_transfer'
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
                            TransferDompet $record
                        ): ?string => filled(
                            $record->bukti_transfer
                        )
                            ? Storage::disk('public')->url(
                                $record->bukti_transfer
                            )
                            : null
                    )
                    ->openUrlInNewTab()
                    ->toggleable(),

                Tables\Columns\TextColumn::make(
                    'sumber_pencatatan'
                )
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            TransferDompet::
                                daftarSumberPencatatan()[
                                    $state
                                ] ?? ucfirst($state)
                    )
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make(
                    'diselesaikan_pada'
                )
                    ->label('Diselesaikan')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->placeholder('Belum selesai')
                    ->sortable()
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
                    'dompet_asal_id'
                )
                    ->label('Dompet Asal')
                    ->relationship(
                        name: 'dompetAsal',
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

                Tables\Filters\SelectFilter::make(
                    'dompet_tujuan_id'
                )
                    ->label('Dompet Tujuan')
                    ->relationship(
                        name: 'dompetTujuan',
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

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Transfer')
                    ->options(
                        TransferDompet::daftarStatus()
                    )
                    ->native(false),

                Tables\Filters\SelectFilter::make(
                    'sumber_pencatatan'
                )
                    ->label('Sumber Pencatatan')
                    ->options(
                        TransferDompet::
                            daftarSumberPencatatan()
                    )
                    ->native(false),

                Tables\Filters\TernaryFilter::make(
                    'biaya_administrasi'
                )
                    ->label('Biaya Administrasi')
                    ->placeholder('Semua transfer')
                    ->trueLabel('Memiliki biaya admin')
                    ->falseLabel('Tanpa biaya admin')
                    ->queries(
                        true: fn (
                            Builder $query
                        ): Builder => $query
                            ->where('biaya_admin', '>', 0),

                        false: fn (
                            Builder $query
                        ): Builder => $query
                            ->where('biaya_admin', 0),

                        blank: fn (
                            Builder $query
                        ): Builder => $query
                    )
                    ->native(false),

                Tables\Filters\Filter::make('periode')
                    ->label('Periode Transfer')
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
                                $data['tanggal_mulai']
                                    ?? null,
                                fn (
                                    Builder $query,
                                    $tanggal
                                ): Builder => $query
                                    ->whereDate(
                                        'tanggal_transfer',
                                        '>=',
                                        $tanggal
                                    )
                            )
                            ->when(
                                $data['tanggal_selesai']
                                    ?? null,
                                fn (
                                    Builder $query,
                                    $tanggal
                                ): Builder => $query
                                    ->whereDate(
                                        'tanggal_transfer',
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
                                'Transfer berhasil dihapus'
                            )
                            ->body(
                                'Saldo dompet akan dihitung ulang secara otomatis.'
                            )
                    ),

                Tables\Actions\RestoreAction::make()
                    ->label('Pulihkan')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(
                                'Transfer berhasil dipulihkan'
                            )
                            ->body(
                                'Saldo dompet akan dihitung ulang secara otomatis.'
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
                'tanggal_transfer',
                'desc'
            )
            ->persistFiltersInSession()
            ->striped()
            ->emptyStateHeading(
                'Belum ada transfer antar-dompet'
            )
            ->emptyStateDescription(
                'Tambahkan transfer untuk mencatat perpindahan saldo antara Cash, BRI, DANA, GoPay, ShopeePay, atau dompet lainnya.'
            )
            ->emptyStateIcon(
                'heroicon-o-arrows-right-left'
            );
    }

    /**
     * Query utama resource.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with([
                'pengguna',
                'dompetAsal',
                'dompetTujuan',
            ]);
    }

    /**
     * Badge jumlah transfer yang masih tertunda.
     */
    public static function getNavigationBadge(): ?string
    {
        $jumlahTertunda = TransferDompet::query()
            ->where(
                'status',
                TransferDompet::STATUS_TERTUNDA
            )
            ->count();

        return $jumlahTertunda > 0
            ? (string) $jumlahTertunda
            : null;
    }

    /**
     * Warna badge navigasi.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Atribut yang dapat dicari melalui pencarian global.
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'kode_transfer',
            'catatan',
            'pengguna.name',
            'pengguna.email',
            'dompetAsal.nama_dompet',
            'dompetTujuan.nama_dompet',
        ];
    }

    /**
     * Membuat daftar pilihan dompet berdasarkan pengguna.
     */
    protected static function opsiDompet(
        Get $get,
        ?TransferDompet $record,
        ?int $kecualiId = null
    ): array {
        $penggunaId = (int) $get('pengguna_id');

        if ($penggunaId <= 0) {
            return [];
        }

        $dompetTersimpan = array_filter([
            $record?->dompet_asal_id,
            $record?->dompet_tujuan_id,
        ]);

        return Dompet::withTrashed()
            ->where('pengguna_id', $penggunaId)
            ->where(
                function (
                    Builder $query
                ) use ($dompetTersimpan): void {
                    $query
                        ->where(function (
                            Builder $query
                        ): void {
                            $query
                                ->where('aktif', true)
                                ->whereNull('deleted_at');
                        });

                    if (! empty($dompetTersimpan)) {
                        $query->orWhereIn(
                            'id',
                            $dompetTersimpan
                        );
                    }
                }
            )
            ->when(
                filled($kecualiId),
                fn (
                    Builder $query
                ): Builder => $query->where(
                    'id',
                    '!=',
                    $kecualiId
                )
            )
            ->orderBy('urutan')
            ->orderBy('nama_dompet')
            ->get()
            ->mapWithKeys(
                function (Dompet $dompet): array {
                    $jenis = static::labelJenisDompet(
                        $dompet->jenis_dompet
                    );

                    $status = [];

                    if (! $dompet->aktif) {
                        $status[] = 'Nonaktif';
                    }

                    if ($dompet->trashed()) {
                        $status[] = 'Dihapus';
                    }

                    $labelStatus = empty($status)
                        ? ''
                        : ' — ' . implode(', ', $status);

                    return [
                        $dompet->id =>
                            "{$dompet->nama_dompet} — {$jenis}{$labelStatus}",
                    ];
                }
            )
            ->all();
    }

    /**
     * Mengubah jenis dompet menjadi label yang mudah dibaca.
     */
    protected static function labelJenisDompet(
        ?string $jenisDompet
    ): string {
        return match ($jenisDompet) {
            'tunai' => 'Tunai',
            'bank' => 'Rekening Bank',
            'dompet_digital' => 'Dompet Digital',
            'lainnya' => 'Lainnya',
            default => 'Jenis tidak tersedia',
        };
    }

    /**
     * Membuat kode transfer unik.
     *
     * Contoh:
     * TRF-20260728-203015-A1B2
     */
    protected static function buatKodeTransfer(): string
    {
        do {
            $kode = 'TRF-'
                . now()->format('Ymd-His')
                . '-'
                . Str::upper(Str::random(4));
        } while (
            TransferDompet::withTrashed()
                ->where('kode_transfer', $kode)
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
            'index' =>
                Pages\ListTransferDompets::route('/'),

            'create' =>
                Pages\CreateTransferDompet::route(
                    '/create'
                ),

            'edit' =>
                Pages\EditTransferDompet::route(
                    '/{record}/edit'
                ),
        ];
    }
}