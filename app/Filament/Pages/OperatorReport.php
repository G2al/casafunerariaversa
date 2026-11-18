<?php

namespace App\Filament\Pages;

use App\Services\CaseReports;
use App\Services\ReportPdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class OperatorReport extends Page
{
    protected static ?string $title = 'Report per operatore';

    protected static string|\UnitEnum|null $navigationGroup = 'Analisi e report';

    protected static ?int $navigationSort = 1;

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

    public function details(): array
    {
        return app(CaseReports::class)->details(auth()->user(), $this->data ?? []);
    }

    public function export()
    {
        abort_unless(static::canAccess(), 403);
        $this->form->getState();

        return app(ReportPdf::class)->download('report-operatori.pdf', 'Report per operatore', [
            'operators' => $this->rows(), 'cases' => $this->details(), 'filters' => $this->data,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label('Esporta PDF')->action('export')];
    }
}
