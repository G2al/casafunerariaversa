<?php

namespace App\Filament\Support;

use App\Models\FuneralCase;
use App\Models\FuneralVehicle;
use App\Models\VehicleCommitment;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\Gate;

class VehicleCommitmentForm
{
    public static function entry(?FuneralCase $record): ?VehicleCommitment
    {
        return $record?->exists ? VehicleCommitment::where('funeral_case_id', $record->id)->first() : null;
    }

    public static function label(?FuneralCase $record): string
    {
        $entry = self::entry($record);

        return match ($entry?->status) {
            'failed', 'uncertain' => 'Riprova invio WhatsApp',
            'sending' => 'Verifica invio in corso',
            'sent' => 'Aggiorna impegno',
            default => 'Impegna mezzo',
        };
    }

    public static function components(): array
    {
        return [
            View::make('filament.forms.vehicle-photos')->columnSpanFull(),
            View::make('filament.forms.vehicle-commitment-status')->columnSpanFull(),
            Actions::make([
                Action::make('commit_vehicle')->label(fn (?FuneralCase $record) => self::label($record))->icon('heroicon-o-truck')
                    ->databaseTransaction(false)
                    ->visible(fn (string $operation, ?FuneralCase $record) => in_array($operation, ['create', 'edit'], true)
                        && ($record ? ! $record->trashed() && Gate::allows('update', $record) : Gate::allows('create', FuneralCase::class)))
                    ->requiresConfirmation()->modalHeading('Conferma impegno del mezzo')
                    ->modalContent(fn (Get $get, ?FuneralCase $record) => view('filament.forms.vehicle-commitment-confirm', [
                        'deceased' => trim($get('first_name').' '.$get('last_name')),
                        'vehicle' => FuneralVehicle::find($get('vehicle_id')),
                        'transportAt' => $get('transport_at'), 'entry' => self::entry($record),
                    ]))->modalSubmitActionLabel('Salva e invia WhatsApp')
                    ->action(fn ($livewire) => $livewire->commitVehicle()),
            ])->columnSpanFull(),
        ];
    }
}
