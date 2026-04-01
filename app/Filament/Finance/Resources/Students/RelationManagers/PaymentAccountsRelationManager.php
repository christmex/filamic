<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\RelationManagers;

use App\Models\Student;
use App\Models\StudentPaymentAccount;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * @method Student getOwnerRecord()
 */
class PaymentAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentAccounts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('school_id')
                ->label('Unit Sekolah')
                ->relationship('school', 'name')
                ->required()
                ->distinct()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->columnSpanFull()
                ->relationship('school', 'name', function ($query) {
                    $query->where('branch_id', Filament::getTenant()->getKey());
                })
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('student_id', $this->getOwnerRecord()->getKey())
                ),
            TextInput::make('monthly_fee_amount')
                ->label('Nominal SPP Bulanan')
                // ->numeric()
                ->regex('/^\d+$/')
                ->prefix('Rp')
                ->placeholder('0')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->minValue(0)
                ->default(0)
                ->required(),
            TextInput::make('book_fee_amount')
                ->label('Nominal Biaya Buku')
                // ->numeric()
                ->regex('/^\d+$/')
                ->prefix('Rp')
                ->placeholder('0')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
                ->minValue(0)
                ->default(0)
                ->required(),
            TextInput::make('monthly_fee_virtual_account')
                ->label('VA SPP Bulanan')
                ->placeholder('Contoh: 103023001')
                ->prefixIcon('tabler-credit-card')
                // ->numeric()
                ->regex('/^\d+$/')
                ->minLength(5)
                ->maxLength(20)
                ->unique(ignoreRecord: true)
                ->different('book_fee_virtual_account'),
            TextInput::make('book_fee_virtual_account')
                ->label('VA Biaya Buku')
                ->placeholder('Contoh: 103023001')
                ->prefixIcon('tabler-book')
                // ->numeric()
                ->regex('/^\d+$/')
                ->minLength(5)
                ->maxLength(20)
                ->unique(ignoreRecord: true)
                ->different('monthly_fee_virtual_account'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Akun Pembayaran')
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('school.name')
                    ->label('Unit Sekolah'),
                TextColumn::make('monthly_fee_virtual_account'),
                TextColumn::make('monthly_fee_amount')
                    ->money('IDR'),
                TextColumn::make('book_fee_virtual_account'),
                TextColumn::make('book_fee_amount')
                    ->money('IDR'),
                // TextColumn::make('status')
                //     ->badge(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (StudentPaymentAccount $studentPaymentAccount) => $this->getOwnerRecord()->school_id === $studentPaymentAccount->school_id),
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn () => $this->getOwnerRecord()->isActive())
                    ->label('Buat Akun Pembayaran'),
            ]);
    }
}
