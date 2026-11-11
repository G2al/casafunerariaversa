<?php

namespace App\Filament\Support;

use App\Models\FuneralCase;
use App\Models\FuneralStaffMember;
use App\Models\FuneralVehicle;
use App\Models\VehicleCommitment;
use App\Services\ReservationConflicts;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
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

    public static function components(): array
    {
        return [View::make('filament.forms.vehicle-photos')->columnSpanFull(), View::make('filament.forms.vehicle-conflicts')->columnSpanFull(),
            Toggle::make('override_vehicle_conflict')->visible(fn (Get $get, ?FuneralCase $record) => app(ReservationConflicts::class)->vehicles($get('vehicle_id'), $get('transport_at'), $get('transport_ends_at'), $record?->id)->isNotEmpty())->label('Confermo a mia responsabilità l’impegno del mezzo anche in sovrapposizione')->default(false)->dehydrated(false),
            View::make('filament.forms.vehicle-commitment-status')->columnSpanFull(),
            Actions::make([Action::make('commit_vehicle')->label('Conferma impegno e apri WhatsApp')->icon('heroicon-o-chat-bubble-left-right')->databaseTransaction(false)
                ->visible(fn (string $operation, ?FuneralCase $record) => $operation === 'edit' && $record && ! $record->trashed() && Gate::allows('update', $record))
                ->unsavedChangesAlert(false)->requiresConfirmation()->modalHeading('Conferma impegno del mezzo')
                ->modalContent(fn (Get $get, ?FuneralCase $record) => view('filament.forms.vehicle-commitment-confirm', [
                    'deceased' => trim($get('first_name').' '.$get('last_name')), 'vehicle' => FuneralVehicle::find($get('vehicle_id')),
                    'director' => FuneralStaffMember::find($get('technical_director_id'))?->name, 'transportAt' => $get('transport_at'), 'endsAt' => $get('transport_ends_at'), 'forced' => (bool) $get('override_vehicle_conflict'),
                ]))->modalSubmitActionLabel('Conferma e apri WhatsApp')->action(fn ($livewire) => $livewire->commitVehicle())])->columnSpanFull(),
        ];
    }
}
