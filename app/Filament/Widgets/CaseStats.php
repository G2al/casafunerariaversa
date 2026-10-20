<?php

namespace App\Filament\Widgets;

use App\Services\CaseReports;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CaseStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $data = app(CaseReports::class)->summary(auth()->user(), $this->pageFilters ?? []);

        return [
            Stat::make(auth()->user()->isAdmin() ? 'Pratiche totali' : 'Le mie pratiche', $data['total'])->description('Nel periodo selezionato, incluso archivio'),
            Stat::make('Nuove oggi', $data['today']), Stat::make('Create nel mese', $data['month']),
            Stat::make('In lavorazione', $data['in_progress'])->color('warning'), Stat::make('Completate', $data['completed'])->color('success'), Stat::make('Archiviate / cestino', $data['archived']),
        ];
    }
}
