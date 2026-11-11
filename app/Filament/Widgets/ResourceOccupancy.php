<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FuneralCaseResource;
use App\Models\FuneralCase;
use App\Models\FuneralVehicle;
use App\Models\Room;
use App\Models\VehicleCommitment;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

class ResourceOccupancy extends Widget
{
    protected string $view = 'filament.widgets.resource-occupancy';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public function resources(): array
    {
        $now = now();
        $result = ['Mezzi' => [], 'Stanze' => []];
        $vehicleBookings = VehicleCommitment::with('funeralCase')->where('status', 'committed')->whereHas('funeralCase')->where('ends_at', '>', $now)->orderBy('starts_at')->get()->groupBy('vehicle_id');
        $roomBookings = FuneralCase::whereNotNull('room_id')->where('viewing_ends_at', '>', $now)->orderBy('viewing_starts_at')->get()->groupBy('room_id');
        foreach (FuneralVehicle::where('is_active', true)->orderBy('plate')->get() as $vehicle) {
            $rows = [];
            foreach ($vehicleBookings->get($vehicle->id, collect()) as $b) {
                $rows[] = $this->booking($b->funeralCase, $b->starts_at, $b->ends_at, $b->snapshot['director'] ?? null);
            }
            $result['Mezzi'][] = ['name' => $vehicle->name.' · '.$vehicle->plate, 'rows' => $rows];
        }
        foreach (Room::orderBy('name')->get() as $room) {
            $rows = [];
            foreach ($roomBookings->get($room->id, collect()) as $case) {
                $rows[] = $this->booking($case, $case->viewing_starts_at, $case->viewing_ends_at);
            }
            $result['Stanze'][] = ['name' => $room->name, 'rows' => $rows];
        }

        return $result;
    }

    private function booking($case, $start, $end, ?string $director = null): array
    {
        $canView = Gate::allows('view', $case);
        $current = $start->lte(now());
        $total = max(1, $end->getTimestamp() - $start->getTimestamp());

        return ['current' => $current, 'percent' => max(0, min(100, round((now()->getTimestamp() - $start->getTimestamp()) / $total * 100))),
            'label' => $canView ? $case->code.' · '.$case->full_name : 'Altra pratica', 'director' => $canView ? $director : null,
            'url' => $canView ? FuneralCaseResource::getUrl('edit', ['record' => $case]) : null,
            'start' => $start->format('d/m/Y H:i'), 'end' => $end->format('d/m/Y H:i')];
    }
}
