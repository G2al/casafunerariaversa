<?php

namespace App\Filament\Widgets;

use App\Services\CaseReports;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class MonthlyCases extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Andamento degli ultimi 12 mesi';

    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = app(CaseReports::class)->monthly(auth()->user(), $this->pageFilters ?? []);

        return ['datasets' => [['label' => 'Pratiche', 'data' => array_values($months), 'backgroundColor' => '#b28b43']], 'labels' => array_map(fn ($month) => Carbon::parse($month.'-01')->translatedFormat('M Y'), array_keys($months))];
    }
}
