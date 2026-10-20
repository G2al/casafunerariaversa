<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Panoramica pratiche';

    protected static string|\UnitEnum|null $navigationGroup = 'Operatività';

    protected static ?int $navigationSort = 1;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('date_field')->label('Periodo riferito a')->options(['registered_at' => 'Presa in carico', 'created_at' => 'Creazione pratica', 'death_date' => 'Decesso'])->default('registered_at'),
            DatePicker::make('from')->label('Dal')->displayFormat('d/m/Y'), DatePicker::make('until')->label('Al')->displayFormat('d/m/Y')->afterOrEqual('from'),
        ]);
    }
}
