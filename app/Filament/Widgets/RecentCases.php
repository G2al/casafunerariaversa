<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FuneralCaseResource;
use App\Models\FuneralCase;
use App\Services\CaseReports;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class RecentCases extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Pratiche recenti';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table->query(app(CaseReports::class)->query(auth()->user(), $this->pageFilters ?? [])->whereNull('deleted_at'))->columns([
            TextColumn::make('code')->label('Codice'), TextColumn::make('last_name')->label('Cognome')->placeholder('Bozza'), TextColumn::make('first_name')->label('Nome'), TextColumn::make('type')->label('Procedura')->badge(), TextColumn::make('status')->label('Stato')->badge(), TextColumn::make('registered_at')->label('Presa in carico')->dateTime('d/m/Y H:i'),
        ])->defaultSort('created_at', 'desc')->paginated([5, 10])->defaultPaginationPageOption(5)->recordUrl(fn (FuneralCase $record) => FuneralCaseResource::getUrl('edit', ['record' => $record]));
    }
}
