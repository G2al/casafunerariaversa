<?php

namespace App\Filament\Pages;

use App\Services\CaseReports;
use App\Services\CsvExport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class OperatorReport extends Page
{
    protected static ?string $title = 'Report per operatore';

    protected static string|\UnitEnum|null $navigationGroup = 'Amministrazione';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.pages.operator-report';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['from' => now()->startOfYear()->format('Y-m-d'), 'until' => today()->format('Y-m-d'), 'date_field' => 'registered_at']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(3)->components([
            Select::make('date_field')->label('Periodo riferito a')->options(['registered_at' => 'Presa in carico', 'created_at' => 'Creazione', 'death_date' => 'Decesso'])->required(),
            DatePicker::make('from')->label('Dal')->required()->displayFormat('d/m/Y'), DatePicker::make('until')->label('Al')->required()->afterOrEqual('from')->displayFormat('d/m/Y'),
        ])->live();
    }

    public function rows(): array
    {
        return app(CaseReports::class)->operators(auth()->user(), $this->data ?? []);
    }

    public function months(): array
    {
        return app(CaseReports::class)->monthly(auth()->user(), $this->data ?? [], false);
    }

    public function export()
    {
        abort_unless(static::canAccess(), 403);
        $this->form->getState();
        $rows = $this->rows();

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Operatore', 'Create', 'Assegnate', 'Bozze', 'In lavorazione', 'Completate', 'Archiviate / cestino', 'Cremazioni', 'Trasporti'], ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map(app(CsvExport::class)->cell(...), array_values($row)), ';', '"', '');
            }
            fclose($stream);
        }, 'report-operatori.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label('Esporta CSV')->action('export')];
    }
}
