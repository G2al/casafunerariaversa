<?php

namespace App\Filament\Pages;

use App\Services\ReportPdf;
use App\Services\ServiceOrderReports;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class ServiceOrderReport extends Page
{
    protected static ?string $title = 'Report casse e materiali';

    protected static string|\UnitEnum|null $navigationGroup = 'Analisi e report';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected string $view = 'filament.pages.service-order-report';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['from' => now()->startOfMonth()->format('Y-m-d'), 'until' => today()->format('Y-m-d')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(2)->components([
            DatePicker::make('from')->label('Conferme dal')->required(),
            DatePicker::make('until')->label('Al')->required()->afterOrEqual('from'),
        ])->live();
    }

    public function report(): array
    {
        return app(ServiceOrderReports::class)->data($this->data ?? []);
    }

    public function export()
    {
        abort_unless(static::canAccess(), 403);
        $this->form->getState();

        return app(ReportPdf::class)->download('casse-e-materiali.pdf', 'Casse e materiali confermati', $this->report());
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label('Esporta PDF')->icon('heroicon-o-arrow-down-tray')->action('export')];
    }
}
