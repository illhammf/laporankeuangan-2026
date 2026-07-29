<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\TransaksiResource;
use App\Models\Transaksi;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TransaksiTerbaruWidget extends BaseWidget
{
    /**
     * Urutan widget pada dashboard.
     */
    protected static ?int $sort = 7;

    /**
     * Widget memenuhi lebar dashboard.
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Menampilkan tabel transaksi terbaru.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaksi::query()
                    ->with([
                        'pengguna',
                        'dompet',
                        'kategori.kategoriInduk',
                    ])
                    ->orderByDesc('tanggal_transaksi')
                    ->orderByDesc('id')
                    ->limit(10)
            )
            ->heading('Transaksi Terbaru')
            ->description(
                'Sepuluh transaksi terakhir dari seluruh pengguna.'
            )
            ->columns([
                Tables\Columns\TextColumn::make(
                    'tanggal_transaksi'
                )
                    ->label('Tanggal')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->description(
                        fn (
                            Transaksi $record
                        ): ?string => $record
                            ->tanggal_transaksi
                            ?->diffForHumans()
                    )
                    ->icon('heroicon-m-calendar-days')
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'nama_transaksi'
                )
                    ->label('Transaksi')
                    ->description(
                        fn (
                            Transaksi $record
                        ): string => $record
                            ->kode_transaksi
                    )
                    ->weight('bold')
                    ->searchable([
                        'nama_transaksi',
                        'kode_transaksi',
                        'pihak_terkait',
                        'lokasi',
                    ])
                    ->limit(35)
                    ->tooltip(
                        fn (
                            Transaksi $record
                        ): string => $record
                            ->nama_transaksi
                    ),

                Tables\Columns\TextColumn::make(
                    'pengguna.name'
                )
                    ->label('Pemilik')
                    ->description(
                        fn (
                            Transaksi $record
                        ): ?string => $record
                            ->pengguna
                            ?->email
                    )
                    ->icon('heroicon-m-user')
                    ->searchable([
                        'name',
                        'email',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'dompet.nama_dompet'
                )
                    ->label('Dompet')
                    ->description(
                        fn (
                            Transaksi $record
                        ): string => $this
                            ->labelJenisDompet(
                                $record
                                    ->dompet
                                    ?->jenis_dompet
                            )
                    )
                    ->icon(
                        fn (
                            Transaksi $record
                        ): string => $record
                            ->dompet
                            ?->ikon
                            ?: 'heroicon-m-wallet'
                    )
                    ->placeholder(
                        'Dompet tidak tersedia'
                    )
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'kategori.nama_kategori'
                )
                    ->label('Kategori')
                    ->description(
                        function (
                            Transaksi $record
                        ): ?string {
                            $kategoriInduk = $record
                                ->kategori
                                ?->kategoriInduk
                                ?->nama_kategori;

                            return $kategoriInduk !== null
                                ? "Induk: {$kategoriInduk}"
                                : null;
                        }
                    )
                    ->icon(
                        fn (
                            Transaksi $record
                        ): string => $record
                            ->kategori
                            ?->ikon
                            ?: 'heroicon-m-tag'
                    )
                    ->placeholder(
                        'Kategori tidak tersedia'
                    )
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'jenis_transaksi'
                )
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            string $state
                        ): string => match ($state) {
                            Transaksi::JENIS_PEMASUKAN =>
                                'Pemasukan',

                            Transaksi::JENIS_PENGELUARAN =>
                                'Pengeluaran',

                            default => ucfirst($state),
                        }
                    )
                    ->color(
                        fn (
                            string $state
                        ): string => match ($state) {
                            Transaksi::JENIS_PEMASUKAN =>
                                'success',

                            Transaksi::JENIS_PENGELUARAN =>
                                'danger',

                            default => 'gray',
                        }
                    )
                    ->icon(
                        fn (
                            string $state
                        ): string => match ($state) {
                            Transaksi::JENIS_PEMASUKAN =>
                                'heroicon-m-arrow-trending-up',

                            Transaksi::JENIS_PENGELUARAN =>
                                'heroicon-m-arrow-trending-down',

                            default =>
                                'heroicon-m-arrows-up-down',
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('nominal')
                    ->label('Nominal')
                    ->formatStateUsing(
                        function (
                            mixed $state,
                            Transaksi $record
                        ): string {
                            $tanda = $record
                                ->jenis_transaksi
                                === Transaksi::JENIS_PEMASUKAN
                                    ? '+ '
                                    : '- ';

                            return $tanda
                                . $this->formatRupiah(
                                    (float) $state
                                );
                        }
                    )
                    ->color(
                        fn (
                            Transaksi $record
                        ): string => $record
                            ->jenis_transaksi
                            === Transaksi::JENIS_PEMASUKAN
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
                        fn (
                            string $state
                        ): string => $this
                            ->labelStatus($state)
                    )
                    ->color(
                        fn (
                            string $state
                        ): string => match ($state) {
                            Transaksi::STATUS_SELESAI =>
                                'success',

                            Transaksi::STATUS_TERTUNDA =>
                                'warning',

                            Transaksi::STATUS_DIBATALKAN =>
                                'danger',

                            default => 'gray',
                        }
                    )
                    ->icon(
                        fn (
                            string $state
                        ): string => match ($state) {
                            Transaksi::STATUS_SELESAI =>
                                'heroicon-m-check-circle',

                            Transaksi::STATUS_TERTUNDA =>
                                'heroicon-m-clock',

                            Transaksi::STATUS_DIBATALKAN =>
                                'heroicon-m-no-symbol',

                            default =>
                                'heroicon-m-question-mark-circle',
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

                Tables\Columns\IconColumn::make(
                    'transaksi_rutin'
                )
                    ->label('Rutin')
                    ->boolean()
                    ->trueIcon(
                        'heroicon-m-arrow-path-rounded-square'
                    )
                    ->falseIcon('heroicon-m-minus-circle')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make(
                    'sumber_pencatatan'
                )
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            string $state
                        ): string => Transaksi::
                            daftarSumberPencatatan()[
                                $state
                            ] ?? ucfirst($state)
                    )
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make(
                    'lihat_semua_transaksi'
                )
                    ->label('Lihat Semua')
                    ->icon(
                        'heroicon-m-arrow-top-right-on-square'
                    )
                    ->color('gray')
                    ->url(
                        TransaksiResource::getUrl('index')
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make(
                    'buka_transaksi'
                )
                    ->label('Buka')
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary')
                    ->url(
                        fn (
                            Transaksi $record
                        ): string => TransaksiResource::getUrl(
                            'edit',
                            [
                                'record' => $record,
                            ]
                        )
                    ),
            ])
            ->recordUrl(
                fn (
                    Transaksi $record
                ): string => TransaksiResource::getUrl(
                    'edit',
                    [
                        'record' => $record,
                    ]
                )
            )
            ->recordClasses(
                fn (
                    Transaksi $record
                ): ?string => match (
                    $record->status
                ) {
                    Transaksi::STATUS_TERTUNDA =>
                        'border-s-2 border-warning-500',

                    Transaksi::STATUS_DIBATALKAN =>
                        'opacity-60',

                    default => null,
                }
            )
            ->poll('30s')
            ->paginated(false)
            ->defaultSort(
                'tanggal_transaksi',
                'desc'
            )
            ->striped()
            ->emptyStateHeading(
                'Belum ada transaksi'
            )
            ->emptyStateDescription(
                'Transaksi terbaru akan ditampilkan di sini setelah data pemasukan atau pengeluaran dibuat.'
            )
            ->emptyStateIcon(
                'heroicon-o-arrows-right-left'
            );
    }

    /**
     * Label jenis dompet.
     */
    private function labelJenisDompet(
        ?string $jenisDompet
    ): string {
        return match ($jenisDompet) {
            'tunai' => 'Tunai',
            'bank' => 'Rekening Bank',
            'dompet_digital' => 'Dompet Digital',
            'lainnya' => 'Dompet Lainnya',
            default => 'Jenis tidak tersedia',
        };
    }

    /**
     * Label status transaksi.
     */
    private function labelStatus(
        string $status
    ): string {
        return match ($status) {
            Transaksi::STATUS_SELESAI =>
                'Selesai',

            Transaksi::STATUS_TERTUNDA =>
                'Tertunda',

            Transaksi::STATUS_DIBATALKAN =>
                'Dibatalkan',

            default => ucfirst($status),
        };
    }

    /**
     * Format mata uang Rupiah.
     */
    private function formatRupiah(
        float $nominal
    ): string {
        return 'Rp '
            . number_format(
                abs($nominal),
                0,
                ',',
                '.'
            );
    }
}