<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Support\Enums\Platform;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\View\Components\ModalComponent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Component as LivewireComponent;
use Livewire\Features\SupportTesting\Testable;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            if (! str_ends_with($request->path(), 'login')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($request->input('email') . $request->ip());
        });

        Filament::serving(function () {
            Filament::getCurrentPanel()
                ->maxContentWidth(Width::Full)
                ->sidebarWidth('16rem')
                ->brandLogo(asset('logo_basic_digital.svg'))
                ->font('Inter')
                ->readOnlyRelationManagersOnResourceViewPagesByDefault(false)
                ->viteTheme('resources/css/filament/admin/theme.css')
                ->brandLogoHeight('1rem')
                ->spa()
                // ->sidebarCollapsibleOnDesktop()
                ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
                ->globalSearchFieldSuffix(fn (): ?string => match (Platform::detect()) {
                    Platform::Windows, Platform::Linux => 'CTRL+K',
                    Platform::Mac => '⌘K',
                    default => null,
                })
                ->databaseTransactions();
        });

        Section::configureUsing(fn (Section $section) => $section
            ->columnSpanFull()
            // ->columns(2)
        );

        Tabs::configureUsing(fn (Tabs $tabs) => $tabs
            ->columnSpanFull()
            // ->columns(2)
        );

        TextInput::configureUsing(function (TextInput $textInput) {
            return $textInput
                ->maxLength(2 ** 8 - 1); // 255 (MySQL VARCHAR(255), Laravel 'string')
        });

        Textarea::configureUsing(fn (Textarea $textArea) => $textArea
            ->maxLength(2 ** 16 - 1) // 65,535 (MySQL TEXT, Laravel 'text')
            ->rows(4));

        CreateAction::configureUsing(function (CreateAction $action) {
            $action->icon('tabler-plus');
        }, isImportant: true);

        ViewAction::configureUsing(function (ViewAction $action) {
            $action
                ->iconButton()
                ->icon('heroicon-o-chevron-right');
        }, isImportant: true);

        Entry::configureUsing(fn (Entry $field) => $field
            ->placeholder('None')
        );

        Action::configureUsing(function (Action $action) {
            $action->size(Size::Small);
        }, isImportant: true);

        ActionGroup::configureUsing(function (ActionGroup $actionGroup) {
            $actionGroup->size(Size::Small);
        }, isImportant: true);

        Testable::macro('ray', function (): Testable {
            /** @var LivewireComponent $livewire */
            $livewire = $this->instance();

            ray()->table([ // @phpstan-ignore-line
                'Component' => get_class($livewire),
                'Data' => $livewire->all(),
                'Errors' => Arr::undot($livewire->getErrorBag()->getMessages()),
            ]);

            return $this;
        });

        ModalComponent::closedByClickingAway(false);
    }
}
