<?php

namespace App\Filament\Resources;

use App\Models\ServiceOrder;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceOrderResource extends Resource
{
    protected static ?string $model = ServiceOrder::class;

    protected static ?string $modelLabel = 'Ordine di servizio';

    protected static ?string $pluralModelLabel = 'Ordini di servizio';

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Operatività';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(! auth()->user()?->isAdmin(), fn ($q) => $q->where('status', '!=', 'draft'));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('case_code')->label('Pratica')->searchable(),
            TextColumn::make('deceased_name')->label('Defunto')->searchable(),
            TextColumn::make('coffin_name')->label('Cassa')->searchable(),
            TextColumn::make('coffin_type')->label('Confezionamento')->formatStateUsing(fn ($state) => ServiceOrder::coffinTypes()[$state] ?? $state),
            TextColumn::make('status')->label('Stato')->badge()->formatStateUsing(fn ($state) => ['draft' => 'Bozza', 'submitted' => 'Da preparare', 'confirmed' => 'Confermato'][$state] ?? $state)->color(fn ($state) => ['draft' => 'gray', 'submitted' => 'warning', 'confirmed' => 'success'][$state] ?? 'gray'),
            TextColumn::make('submitted_at')->label('Ricevuto')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('confirmed_at')->label('Confermato')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([SelectFilter::make('status')->label('Stato')->options(['draft' => 'Bozza', 'submitted' => 'Da preparare', 'confirmed' => 'Confermato'])])
            ->defaultSort('submitted_at', 'desc')->recordUrl(fn ($record) => self::getUrl('view', ['record' => $record]))
            ->recordActions([Action::make('open')->label('Apri ordine')->url(fn ($record) => self::getUrl('view', ['record' => $record]))]);
    }

    public static function getPages(): array
    {
        return ['index' => ServiceOrderResource\Pages\ListServiceOrders::route('/'), 'view' => ServiceOrderResource\Pages\ManageServiceOrder::route('/{record}')];
    }
}
