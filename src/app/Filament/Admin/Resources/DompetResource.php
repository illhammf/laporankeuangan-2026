<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DompetResource\Pages;
use App\Models\Dompet;
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

class DompetResource extends Resource
{
    protected static ?string $model = Dompet::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Manajemen Keuangan';

    protected static ?string $navigationLabel = 'Dompet';

    protected static ?string $modelLabel = 'Dompet';

    protected static ?string $pluralModelLabel = 'Dompet';

    protected static ?string $recordTitleAttribute = 'nama_dompet';

    protected static ?int $navigationSort = 1;

    /**
     * Form tambah dan edit dompet.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pemilik')
                    ->description(
                        'Tentukan pengguna yang memiliki dompet, rekening, atau akun pembayaran ini.'
                    )
                    ->icon('heroicon-o-user')
                    ->schema([
                        Forms\Components\Select::make('pengguna_id')
                            ->label('Pemilik Dompet')
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
                            ->default(fn (): ?int => auth()->id())
                            ->required()
                            ->native(false)
                            ->helperText(
                                'Dompet hanya dapat digunakan untuk transaksi milik pengguna yang dipilih.'
                            ),
                    ]),

                Forms\Components\Section::make('Informasi Dompet')
                    ->description(
                        'Isi identitas utama tempat penyimpanan uang.'
                    )
                    ->icon('heroicon-o-wallet')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\TextInput::make('nama_dompet')
                            ->label('Nama Dompet')
                            ->placeholder('Contoh: Cash, BRI, DANA')
                            ->required()
                            ->maxLength(100)
                            ->rules([
                                fn (
                                    Get $get,
                                    ?Dompet $record
                                ) => Rule::unique(
                                    table: 'dompet',
                                    column: 'nama_dompet'
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
                                'unique' => 'Pengguna tersebut sudah memiliki dompet dengan nama yang sama.',
                            ]),

                        Forms\Components\Select::make('jenis_dompet')
                            ->label('Jenis Dompet')
                            ->options([
                                'tunai' => 'Tunai',
                                'bank' => 'Rekening Bank',
                                'dompet_digital' => 'Dompet Digital',
                                'lainnya' => 'Lainnya',
                            ])
                            ->default('tunai')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    Set $set,
                                    ?string $state
                                ): void {
                                    if ($state === 'tunai') {
                                        $set('nomor_akun', null);
                                    }
                                }
                            ),

                        Forms\Components\TextInput::make('nomor_akun')
                            ->label('Nomor Rekening atau Akun')
                            ->placeholder(
                                'Contoh: nomor rekening atau nomor telepon'
                            )
                            ->maxLength(100)
                            ->visible(
                                fn (Get $get): bool => in_array(
                                    $get('jenis_dompet'),
                                    [
                                        'bank',
                                        'dompet_digital',
                                        'lainnya',
                                    ],
                                    true
                                )
                            )
                            ->helperText(
                                'Boleh dikosongkan apabila tidak ingin menyimpan nomor rekening.'
                            ),

                        Forms\Components\Select::make('mata_uang')
                            ->label('Mata Uang')
                            ->options([
                                'IDR' => 'Rupiah Indonesia (IDR)',
                            ])
                            ->default('IDR')
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('saldo_awal')
                            ->label('Saldo Awal')
                            ->prefix('Rp')
                            ->placeholder('0')
                            ->numeric()
                            ->minValue(0)
                            ->step(1000)
                            ->default(0)
                            ->required()
                            ->helperText(
                                'Saldo saat dompet pertama kali dicatat. Saldo berjalan dihitung otomatis dari transaksi.'
                            ),

                        Forms\Components\TextInput::make('urutan')
                            ->label('Urutan Tampilan')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->helperText(
                                'Angka lebih kecil akan ditampilkan lebih dahulu.'
                            ),
                    ]),

                Forms\Components\Section::make('Tampilan')
                    ->description(
                        'Atur ikon dan warna agar dompet lebih mudah dikenali.'
                    )
                    ->icon('heroicon-o-paint-brush')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->collapsible()
                    ->schema([
                        Forms\Components\Select::make('ikon')
                            ->label('Ikon Dompet')
                            ->options([
                                'heroicon-o-banknotes' => 'Uang Tunai',
                                'heroicon-o-wallet' => 'Dompet',
                                'heroicon-o-building-library' => 'Bank',
                                'heroicon-o-credit-card' => 'Kartu atau Rekening',
                                'heroicon-o-device-phone-mobile' => 'Dompet Digital',
                                'heroicon-o-building-storefront' => 'Usaha',
                            ])
                            ->default('heroicon-o-wallet')
                            ->searchable()
                            ->native(false),

                        Forms\Components\ColorPicker::make('warna')
                            ->label('Warna Dompet')
                            ->default('#16A34A'),
                    ]),

                Forms\Components\Section::make('Status dan Catatan')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Forms\Components\Toggle::make('aktif')
                            ->label('Dompet Aktif')
                            ->default(true)
                            ->inline(false)
                            ->helperText(
                                'Dompet tidak aktif tetap tersimpan, tetapi tidak ditampilkan pada pilihan transaksi baru.'
                            ),

                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->placeholder(
                                'Tambahkan informasi lain mengenai dompet ini.'
                            )
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Tabel daftar dompet.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pengguna.name')
                    ->label('Pemilik')
                    ->description(
                        fn (Dompet $record): ?string => $record
                            ->pengguna
                            ?->email
                    )
                    ->searchable([
                        'name',
                        'email',
                    ])
                    ->sortable()
                    ->icon('heroicon-o-user'),

                Tables\Columns\TextColumn::make('nama_dompet')
                    ->label('Nama Dompet')
                    ->icon(
                        fn (Dompet $record): string => $record->ikon
                            ?: 'heroicon-o-wallet'
                    )
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('jenis_dompet')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'tunai' => 'Tunai',
                            'bank' => 'Rekening Bank',
                            'dompet_digital' => 'Dompet Digital',
                            default => 'Lainnya',
                        }
                    )
                    ->color(
                        fn (string $state): string => match ($state) {
                            'tunai' => 'success',
                            'bank' => 'info',
                            'dompet_digital' => 'warning',
                            default => 'gray',
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('nomor_akun')
                    ->label('Nomor Akun')
                    ->placeholder('Tidak dicantumkan')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Nomor akun berhasil disalin')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('saldo_awal')
                    ->label('Saldo Awal')
                    ->formatStateUsing(
                        fn ($state): string => 'Rp' . number_format(
                            (float) $state,
                            0,
                            ',',
                            '.'
                        )
                    )
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('saldo_saat_ini')
                    ->label('Saldo Saat Ini')
                    ->formatStateUsing(
                        fn ($state): string => 'Rp' . number_format(
                            (float) $state,
                            0,
                            ',',
                            '.'
                        )
                    )
                    ->color(
                        fn ($state): string => (float) $state < 0
                            ? 'danger'
                            : 'success'
                    )
                    ->weight('bold')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\ColorColumn::make('warna')
                    ->label('Warna')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Dihapus')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->placeholder('Tidak dihapus')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->preload(),

                Tables\Filters\SelectFilter::make('jenis_dompet')
                    ->label('Jenis Dompet')
                    ->options([
                        'tunai' => 'Tunai',
                        'bank' => 'Rekening Bank',
                        'dompet_digital' => 'Dompet Digital',
                        'lainnya' => 'Lainnya',
                    ])
                    ->native(false),

                Tables\Filters\TernaryFilter::make('aktif')
                    ->label('Status Dompet')
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
                    ->label('Hapus'),

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
            ->defaultSort('urutan')
            ->striped()
            ->emptyStateHeading('Belum ada dompet')
            ->emptyStateDescription(
                'Tambahkan dompet tunai, rekening bank, atau dompet digital.'
            )
            ->emptyStateIcon('heroicon-o-wallet');
    }

    /**
     * Menghitung saldo aktual setiap dompet.
     *
     * Saldo saat ini:
     * saldo awal
     * + pemasukan selesai
     * - pengeluaran selesai
     * + transfer masuk selesai
     * - transfer keluar selesai
     * - biaya administrasi transfer.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with('pengguna')
            ->select('dompet.*')
            ->selectRaw(
                "
                (
                    dompet.saldo_awal

                    + (
                        SELECT COALESCE(SUM(transaksi.nominal), 0)
                        FROM transaksi
                        WHERE transaksi.dompet_id = dompet.id
                        AND transaksi.jenis_transaksi = 'pemasukan'
                        AND transaksi.status = 'selesai'
                        AND transaksi.deleted_at IS NULL
                    )

                    - (
                        SELECT COALESCE(SUM(transaksi.nominal), 0)
                        FROM transaksi
                        WHERE transaksi.dompet_id = dompet.id
                        AND transaksi.jenis_transaksi = 'pengeluaran'
                        AND transaksi.status = 'selesai'
                        AND transaksi.deleted_at IS NULL
                    )

                    + (
                        SELECT COALESCE(SUM(transfer_dompet.nominal), 0)
                        FROM transfer_dompet
                        WHERE transfer_dompet.dompet_tujuan_id = dompet.id
                        AND transfer_dompet.status = 'selesai'
                        AND transfer_dompet.deleted_at IS NULL
                    )

                    - (
                        SELECT COALESCE(
                            SUM(
                                transfer_dompet.nominal
                                + transfer_dompet.biaya_admin
                            ),
                            0
                        )
                        FROM transfer_dompet
                        WHERE transfer_dompet.dompet_asal_id = dompet.id
                        AND transfer_dompet.status = 'selesai'
                        AND transfer_dompet.deleted_at IS NULL
                    )
                ) AS saldo_saat_ini
                "
            );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDompets::route('/'),
            'create' => Pages\CreateDompet::route('/create'),
            'edit' => Pages\EditDompet::route('/{record}/edit'),
        ];
    }
}