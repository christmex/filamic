<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Schools\Schemas;

use App\Enums\LevelEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('School Information')
                    ->tabs([
                        Tabs\Tab::make('School Identity')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([
                                Select::make('branch_id')
                                    ->relationship('branch', 'name')
                                    ->required(),
                                TextInput::make('name')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                TextInput::make('npsn')->label('NPSN'),
                                TextInput::make('nis_nss_nds')->label('NIS/NSS/NDS'),
                                ToggleButtons::make('level')
                                    ->options(LevelEnum::class)
                                    ->inline()
                                    ->required()
                                    ->columnSpanFull(),
                            ])->columns(2),
                        Tabs\Tab::make('Address & Location')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                TextInput::make('address'),
                                TextInput::make('village'),
                                TextInput::make('subdistrict'),
                                TextInput::make('city'),
                                TextInput::make('province'),
                                TextInput::make('postal_code'),
                            ])->columns(2),
                        Tabs\Tab::make('Contact & Communication')
                            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                            ->schema([
                                TextInput::make('website')
                                    ->url(),
                                TextInput::make('email')->label('Email address')->email(),
                                TextInput::make('telp')->tel()->columnSpanFull(),
                            ])->columns(2),
                    ]),
            ]);
    }
}
