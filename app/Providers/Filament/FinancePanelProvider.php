<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureAcademicPeriodIsReady;
use App\Models\Branch;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class FinancePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('finance')
            ->path('finance')
            ->login()
            ->discoverResources(in: app_path('Filament/Finance/Resources'), for: 'App\Filament\Finance\Resources')
            ->discoverPages(in: app_path('Filament/Finance/Pages'), for: 'App\Filament\Finance\Pages')
            // ->pages([
            //     Dashboard::class,
            // ])
            // ->discoverWidgets(in: app_path('Filament/Finance/Widgets'), for: 'App\Filament\Finance\Widgets')
            // ->widgets([
            //     AccountWidget::class,
            //     FilamentInfoWidget::class,
            // ])
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
                ThrottleRequests::using('login'),
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAcademicPeriodIsReady::class,
            ])
            ->colors([
                'danger' => '#e63946',
                'gray' => '#6b7280',
                'info' => '#457b9d',
                'primary' => '#575DFA',
                'success' => '#10b981',
                'warning' => '#ffb703',
            ])
            ->tenant(Branch::class)
            ->topNavigation()
            ->databaseTransactions()
            ->spa();
    }
}
