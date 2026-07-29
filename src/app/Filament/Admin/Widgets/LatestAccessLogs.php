<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AnggaranBulanan;
use App\Models\Dompet;
use App\Models\Kategori;
use App\Models\Transaksi;
use App\Models\TransferDompet;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class LatestAccessLogs extends BaseWidget
{
    use HasWidgetShield;

    /**
     * Diletakkan setelah seluruh widget keuangan.
     */
    protected static ?int $sort = 100;

    /**
     * Memenuhi seluruh lebar dashboard.
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * Tabel aktivitas sistem terbaru.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->with([
                        'causer',
                        'subject',
                    ])
                    ->latest('created_at')
                    ->latest('id')
                    ->limit(8)
            )
            ->heading('Aktivitas Sistem Terbaru')
            ->description(
                'Delapan aktivitas terakhir yang tercatat pada panel administrasi.'
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime(
                        format: 'd M Y, H:i',
                        timezone: 'Asia/Jakarta'
                    )
                    ->description(
                        fn (Activity $record): string =>
                            $record->created_at
                                ?->timezone('Asia/Jakarta')
                                ->diffForHumans()
                            ?? '-'
                    )
                    ->icon('heroicon-m-clock')
                    ->sortable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Jenis Log')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            static::formatNamaLog($state)
                    )
                    ->color(
                        fn (?string $state): string =>
                            static::warnaNamaLog($state)
                    )
                    ->icon(
                        fn (?string $state): string =>
                            static::ikonNamaLog($state)
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Aktivitas')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            static::formatEvent($state)
                    )
                    ->color(
                        fn (?string $state): string =>
                            static::warnaEvent($state)
                    )
                    ->icon(
                        fn (?string $state): string =>
                            static::ikonEvent($state)
                    )
                    ->placeholder('Aktivitas sistem')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Keterangan')
                    ->formatStateUsing(
                        fn (
                            ?string $state
                        ): string => filled($state)
                            ? Str::ucfirst($state)
                            : 'Tidak ada keterangan'
                    )
                    ->limit(80)
                    ->wrap()
                    ->tooltip(
                        fn (
                            Activity $record
                        ): ?string => $record->description
                    )
                    ->searchable(),

                Tables\Columns\TextColumn::make(
                    'subjek_dipahami'
                )
                    ->label('Objek Data')
                    ->getStateUsing(
                        fn (
                            Activity $record
                        ): string => static::formatSubjek(
                            $record
                        )
                    )
                    ->icon('heroicon-m-document-text')
                    ->wrap()
                    ->placeholder('Aktivitas sistem'),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Pengguna')
                    ->description(
                        fn (
                            Activity $record
                        ): ?string => $record
                            ->causer instanceof User
                                ? $record->causer->email
                                : null
                    )
                    ->formatStateUsing(
                        fn (
                            ?string $state
                        ): string => filled($state)
                            ? $state
                            : 'Sistem'
                    )
                    ->icon(
                        fn (
                            Activity $record
                        ): string => $record->causer !== null
                            ? 'heroicon-m-user-circle'
                            : 'heroicon-m-cpu-chip'
                    )
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make(
                    'subject_type'
                )
                    ->label('Model')
                    ->formatStateUsing(
                        fn (
                            ?string $state
                        ): string => filled($state)
                            ? Str::of($state)
                                ->afterLast('\\')
                                ->headline()
                                ->toString()
                            : 'Sistem'
                    )
                    ->badge()
                    ->color('gray')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID Internal')
                    ->formatStateUsing(
                        fn (
                            mixed $state
                        ): string => filled($state)
                            ? "#{$state}"
                            : '-'
                    )
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                Tables\Columns\TextColumn::make('batch_uuid')
                    ->label('Batch UUID')
                    ->copyable()
                    ->copyMessage(
                        'Batch UUID berhasil disalin'
                    )
                    ->placeholder('Tidak tersedia')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Jenis Log')
                    ->options(
                        Activity::query()
                            ->whereNotNull('log_name')
                            ->distinct()
                            ->orderBy('log_name')
                            ->pluck('log_name', 'log_name')
                            ->mapWithKeys(
                                fn (
                                    string $logName
                                ): array => [
                                    $logName =>
                                        static::formatNamaLog(
                                            $logName
                                        ),
                                ]
                            )
                            ->all()
                    )
                    ->native(false),

                Tables\Filters\SelectFilter::make('event')
                    ->label('Aktivitas')
                    ->options(
                        Activity::query()
                            ->whereNotNull('event')
                            ->distinct()
                            ->orderBy('event')
                            ->pluck('event', 'event')
                            ->mapWithKeys(
                                fn (
                                    string $event
                                ): array => [
                                    $event =>
                                        static::formatEvent(
                                            $event
                                        ),
                                ]
                            )
                            ->all()
                    )
                    ->native(false),

                Tables\Filters\Filter::make('periode')
                    ->label('Periode Aktivitas')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make(
                            'tanggal_mulai'
                        )
                            ->label('Tanggal Mulai')
                            ->native(false),

                        \Filament\Forms\Components\DatePicker::make(
                            'tanggal_selesai'
                        )
                            ->label('Tanggal Selesai')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(
                        fn (
                            $query,
                            array $data
                        ) => $query
                            ->when(
                                $data['tanggal_mulai'] ?? null,
                                fn (
                                    $query,
                                    $tanggal
                                ) => $query->whereDate(
                                    'created_at',
                                    '>=',
                                    $tanggal
                                )
                            )
                            ->when(
                                $data['tanggal_selesai'] ?? null,
                                fn (
                                    $query,
                                    $tanggal
                                ) => $query->whereDate(
                                    'created_at',
                                    '<=',
                                    $tanggal
                                )
                            )
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s')
            ->paginated(false)
            ->striped()
            ->emptyStateHeading(
                'Belum ada aktivitas sistem'
            )
            ->emptyStateDescription(
                'Aktivitas login, perubahan data, dan tindakan administratif akan muncul di bagian ini.'
            )
            ->emptyStateIcon(
                'heroicon-o-shield-check'
            );
    }

    /**
     * Mengubah nama log menjadi label yang mudah dibaca.
     */
    protected static function formatNamaLog(
        ?string $logName
    ): string {
        if (blank($logName)) {
            return 'Sistem';
        }

        return match (Str::lower($logName)) {
            'access' => 'Akses',
            'resource' => 'Resource',
            'resources' => 'Resource',
            'model' => 'Data Model',
            'models' => 'Data Model',
            'notification' => 'Notifikasi',
            'notifications' => 'Notifikasi',
            'default' => 'Umum',

            default => Str::of($logName)
                ->replace([
                    '_',
                    '-',
                ], ' ')
                ->headline()
                ->toString(),
        };
    }

    /**
     * Menentukan warna berdasarkan konfigurasi Filament Logger.
     */
    protected static function warnaNamaLog(
        ?string $logName
    ): string {
        if (blank($logName)) {
            return 'gray';
        }

        foreach (
            static::getLogNameColors()
            as $warna => $namaLog
        ) {
            if ($namaLog === $logName) {
                return $warna;
            }
        }

        return match (Str::lower($logName)) {
            'access' => 'info',
            'resource',
            'resources' => 'primary',
            'model',
            'models' => 'warning',
            'notification',
            'notifications' => 'success',

            default => 'gray',
        };
    }

    /**
     * Menentukan ikon berdasarkan jenis log.
     */
    protected static function ikonNamaLog(
        ?string $logName
    ): string {
        return match (Str::lower((string) $logName)) {
            'access' => 'heroicon-m-key',
            'resource',
            'resources' => 'heroicon-m-folder-open',
            'model',
            'models' => 'heroicon-m-circle-stack',
            'notification',
            'notifications' => 'heroicon-m-bell',

            default => 'heroicon-m-list-bullet',
        };
    }

    /**
     * Mengambil warna log dari konfigurasi package.
     */
    protected static function getLogNameColors(): array
    {
        $customs = [];

        foreach (
            config('filament-logger.custom') ?? []
            as $custom
        ) {
            $warna = $custom['color'] ?? null;
            $namaLog = $custom['log_name'] ?? null;

            if (
                filled($warna)
                && filled($namaLog)
            ) {
                $customs[$warna] = $namaLog;
            }
        }

        $resources = [];

        if (
            config('filament-logger.resources.enabled')
            && filled(
                config(
                    'filament-logger.resources.color'
                )
            )
        ) {
            $resources[
                config(
                    'filament-logger.resources.color'
                )
            ] = config(
                'filament-logger.resources.log_name'
            );
        }

        $models = [];

        if (
            config('filament-logger.models.enabled')
            && filled(
                config(
                    'filament-logger.models.color'
                )
            )
        ) {
            $models[
                config(
                    'filament-logger.models.color'
                )
            ] = config(
                'filament-logger.models.log_name'
            );
        }

        $access = [];

        if (
            config('filament-logger.access.enabled')
            && filled(
                config(
                    'filament-logger.access.color'
                )
            )
        ) {
            $access[
                config(
                    'filament-logger.access.color'
                )
            ] = config(
                'filament-logger.access.log_name'
            );
        }

        $notifications = [];

        if (
            config(
                'filament-logger.notifications.enabled'
            )
            && filled(
                config(
                    'filament-logger.notifications.color'
                )
            )
        ) {
            $notifications[
                config(
                    'filament-logger.notifications.color'
                )
            ] = config(
                'filament-logger.notifications.log_name'
            );
        }

        return array_merge(
            $resources,
            $models,
            $access,
            $notifications,
            $customs
        );
    }

    /**
     * Mengubah event teknis menjadi Bahasa Indonesia.
     */
    protected static function formatEvent(
        ?string $event
    ): string {
        if (blank($event)) {
            return 'Aktivitas Sistem';
        }

        return match (Str::lower($event)) {
            'created',
            'create' => 'Dibuat',

            'updated',
            'update' => 'Diperbarui',

            'deleted',
            'delete' => 'Dihapus',

            'restored',
            'restore' => 'Dipulihkan',

            'forcedeleted',
            'force_deleted',
            'force-delete' => 'Dihapus Permanen',

            'login',
            'logged_in' => 'Masuk',

            'logout',
            'logged_out' => 'Keluar',

            'failed_login',
            'login_failed' => 'Gagal Masuk',

            'viewed',
            'view' => 'Dilihat',

            'downloaded',
            'download' => 'Diunduh',

            'uploaded',
            'upload' => 'Diunggah',

            default => Str::of($event)
                ->replace([
                    '_',
                    '-',
                ], ' ')
                ->headline()
                ->toString(),
        };
    }

    /**
     * Warna badge aktivitas.
     */
    protected static function warnaEvent(
        ?string $event
    ): string {
        return match (Str::lower((string) $event)) {
            'created',
            'create' => 'success',

            'updated',
            'update' => 'info',

            'deleted',
            'delete',
            'forcedeleted',
            'force_deleted',
            'force-delete' => 'danger',

            'restored',
            'restore' => 'success',

            'login',
            'logged_in' => 'primary',

            'logout',
            'logged_out' => 'gray',

            'failed_login',
            'login_failed' => 'danger',

            default => 'gray',
        };
    }

    /**
     * Ikon aktivitas.
     */
    protected static function ikonEvent(
        ?string $event
    ): string {
        return match (Str::lower((string) $event)) {
            'created',
            'create' => 'heroicon-m-plus-circle',

            'updated',
            'update' => 'heroicon-m-pencil-square',

            'deleted',
            'delete',
            'forcedeleted',
            'force_deleted',
            'force-delete' => 'heroicon-m-trash',

            'restored',
            'restore' => 'heroicon-m-arrow-uturn-left',

            'login',
            'logged_in' => 'heroicon-m-arrow-right-end-on-rectangle',

            'logout',
            'logged_out' => 'heroicon-m-arrow-left-start-on-rectangle',

            'failed_login',
            'login_failed' => 'heroicon-m-shield-exclamation',

            default => 'heroicon-m-bolt',
        };
    }

    /**
     * Menampilkan subjek log dalam bentuk yang mudah dipahami.
     */
    protected static function formatSubjek(
        Activity $activity
    ): string {
        if (blank($activity->subject_type)) {
            return 'Sistem';
        }

        $subject = $activity->subject;

        if (! $subject instanceof Model) {
            return Str::of($activity->subject_type)
                ->afterLast('\\')
                ->headline()
                ->toString();
        }

        return match (true) {
            $subject instanceof User =>
                "{$subject->name} — {$subject->email}",

            $subject instanceof Dompet =>
                $subject->nama_dompet,

            $subject instanceof Kategori =>
                $subject->nama_kategori,

            $subject instanceof Transaksi =>
                "{$subject->nama_transaksi} — "
                . $subject->kode_transaksi,

            $subject instanceof TransferDompet =>
                $subject->kode_transfer,

            $subject instanceof AnggaranBulanan =>
                static::formatAnggaranBulanan(
                    $subject
                ),

            default => Str::of(
                $activity->subject_type
            )
                ->afterLast('\\')
                ->headline()
                ->toString(),
        };
    }

    /**
     * Format label objek anggaran.
     */
    protected static function formatAnggaranBulanan(
        AnggaranBulanan $anggaran
    ): string {
        $namaBulan = AnggaranBulanan::daftarBulan()[
            $anggaran->bulan
        ] ?? "Bulan {$anggaran->bulan}";

        return "Anggaran {$namaBulan} "
            . $anggaran->tahun;
    }
}