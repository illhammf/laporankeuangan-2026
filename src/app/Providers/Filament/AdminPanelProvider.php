<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Widgets\ArusKasBulananChart;
use App\Filament\Admin\Widgets\KomposisiPengeluaranChart;
use App\Filament\Admin\Widgets\LatestAccessLogs;
use App\Filament\Admin\Widgets\PemantauanAnggaranWidget;
use App\Filament\Admin\Widgets\PeringatanKeuanganWidget;
use App\Filament\Admin\Widgets\RingkasanKeuanganWidget;
use App\Filament\Admin\Widgets\SaldoDompetWidget;
use App\Filament\Admin\Widgets\TransaksiTerbaruWidget;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Joaopaulolndev\FilamentEditProfile\Pages\EditProfilePage;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            /*
            |--------------------------------------------------------------------------
            | Identitas panel
            |--------------------------------------------------------------------------
            */
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Keuangan Pribadi')
            ->spa()

            /*
            |--------------------------------------------------------------------------
            | Autentikasi
            |--------------------------------------------------------------------------
            */
            ->login()
            ->passwordReset()
            ->profile(
                \App\Filament\Pages\Auth\EditProfile::class,
                isSimple: false
            )

            /*
            |--------------------------------------------------------------------------
            | Tampilan
            |--------------------------------------------------------------------------
            */
            ->defaultThemeMode(ThemeMode::Light)
            ->font('Montserrat')
            ->colors([
                'primary' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
                'gray' => Color::Slate,
            ])
            ->maxContentWidth(MaxWidth::SevenExtraLarge)
            ->sidebarCollapsibleOnDesktop()

            /*
            |--------------------------------------------------------------------------
            | Proteksi operasi admin
            |--------------------------------------------------------------------------
            |
            | Memberikan peringatan ketika admin meninggalkan
            | form yang belum disimpan.
            |
            | Create, edit, delete, dan action Filament akan
            | dibungkus dengan database transaction.
            |
            */
            ->unsavedChangesAlerts()
            ->databaseTransactions()

            /*
            |--------------------------------------------------------------------------
            | Resource
            |--------------------------------------------------------------------------
            */
            ->discoverResources(
                in: app_path('Filament/Admin/Resources'),
                for: 'App\\Filament\\Admin\\Resources'
            )

            /*
            |--------------------------------------------------------------------------
            | Halaman
            |--------------------------------------------------------------------------
            */
            ->discoverPages(
                in: app_path('Filament/Admin/Pages'),
                for: 'App\\Filament\\Admin\\Pages'
            )
            ->pages([
                Pages\Dashboard::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Cluster
            |--------------------------------------------------------------------------
            */
            ->discoverClusters(
                in: app_path('Filament/Admin/Clusters'),
                for: 'App\\Filament\\Admin\\Clusters'
            )

            /*
            |--------------------------------------------------------------------------
            | Widget dashboard
            |--------------------------------------------------------------------------
            |
            | Widget didaftarkan secara eksplisit agar dashboard
            | hanya berisi widget yang memang dibutuhkan.
            |
            | Awcodes OverlookWidget tidak didaftarkan lagi,
            | sehingga kartu Users tidak muncul.
            |
            */
            ->widgets([
                RingkasanKeuanganWidget::class,
                SaldoDompetWidget::class,
                ArusKasBulananChart::class,
                KomposisiPengeluaranChart::class,
                PemantauanAnggaranWidget::class,
                PeringatanKeuanganWidget::class,
                TransaksiTerbaruWidget::class,
                LatestAccessLogs::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Kelompok navigasi
            |--------------------------------------------------------------------------
            */
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Manajemen Keuangan'),

                NavigationGroup::make()
                    ->label('Administration'),
            ])

            /*
            |--------------------------------------------------------------------------
            | Menu pengguna
            |--------------------------------------------------------------------------
            */
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(
                        fn (): string =>
                            auth()->user()?->name
                            ?? 'Profil Saya'
                    )
                    ->url(
                        fn (): string =>
                            EditProfilePage::getUrl()
                    )
                    ->icon('heroicon-m-user-circle'),
            ])

            /*
            |--------------------------------------------------------------------------
            | Plugin
            |--------------------------------------------------------------------------
            |
            | OverlookPlugin dihapus karena sebelumnya digunakan
            | untuk membuat widget Users.
            |
            | UserResource tetap tersedia melalui resource discovery.
            |
            */
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 2,
                        'lg' => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 2,
                        'lg' => 3,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 2,
                        'lg' => 3,
                    ]),

                \Hasnayeen\Themes\ThemesPlugin::make(),

                \Njxqlus\FilamentProgressbar\FilamentProgressbarPlugin::make()
                    ->color('#29b'),

                \DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin::make()
                    ->showEmptyPanelOnMobile(false)
                    ->formPanelPosition('right')
                    ->formPanelWidth('40%')
                    ->emptyPanelBackgroundImageOpacity('70%')
                    ->emptyPanelBackgroundImageUrl(
                        'https://picsum.photos/seed/picsum/1260/750.webp/?blur=1'
                    ),

                \Awcodes\LightSwitch\LightSwitchPlugin::make()
                    ->position(
                        \Awcodes\LightSwitch\Enums\Alignment::BottomCenter
                    )
                    ->enabledOn([
                        'auth.login',
                        'auth.password',
                    ]),

                \Joaopaulolndev\FilamentEditProfile\FilamentEditProfilePlugin::make()
                    ->slug('my-profile')
                    ->setTitle('Profil Saya')
                    ->shouldRegisterNavigation(false)
                    ->shouldShowDeleteAccountForm(false)
                    ->shouldShowSanctumTokens(false)
                    ->shouldShowBrowserSessionsForm()
                    ->shouldShowAvatarForm(),
            ])

            /*
            |--------------------------------------------------------------------------
            | Resource dari package
            |--------------------------------------------------------------------------
            */
            ->resources(
                array_filter([
                    config(
                        'filament-logger.activity_resource'
                    ),
                ])
            )

            /*
            |--------------------------------------------------------------------------
            | Tema Vite
            |--------------------------------------------------------------------------
            */
            ->viteTheme(
                'resources/css/filament/admin/theme.css'
            )

            /*
            |--------------------------------------------------------------------------
            | Middleware panel
            |--------------------------------------------------------------------------
            */
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \Hasnayeen\Themes\Http\Middleware\SetTheme::class,
            ])

            /*
            |--------------------------------------------------------------------------
            | Middleware autentikasi
            |--------------------------------------------------------------------------
            */
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}