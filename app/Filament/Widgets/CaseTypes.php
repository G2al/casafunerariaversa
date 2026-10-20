<?php

namespace App\Filament\Widgets;

use App\Services\CaseReports;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CaseTypes extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Ripartizione delle procedure';

    protected static ?int $sort = 3;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $data = app(CaseReports::class)->summary(auth()->user(), $this->pageFilters ?? []);

        return ['datasets' => [['data' => [$data['cremation'], $data['standard_transport']], 'backgroundColor' => ['#b28b43', '#426779']]], 'labels' => ['Cremazione', 'Trasporto normale']];
    }
}
