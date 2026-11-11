<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FuneralCaseResource;
use App\Models\FuneralCase;
use App\Models\FuneralVehicle;
use App\Models\Room;
use App\Models\VehicleCommitment;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Renderless;

class ResourceOccupancy extends Widget
{
    protected string $view = 'filament.widgets.resource-occupancy';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    #[Renderless]
    public function snapshot(): array
    {
        Gate::authorize('viewAny', FuneralCase::class);
        $now = now();
        $resources = [];
        $vehicles = VehicleCommitment::with('funeralCase:id,operator_id,code,first_name,last_name')->where('status', 'committed')->whereHas('funeralCase')
            ->whereNotNull('starts_at')->where('ends_at', '>', $now)->whereColumn('ends_at', '>', 'starts_at')->orderBy('starts_at')->get(['id', 'vehicle_id', 'funeral_case_id', 'starts_at', 'ends_at', 'snapshot'])->groupBy('vehicle_id');
        $rooms = FuneralCase::whereNotNull('room_id')->whereNotNull('viewing_starts_at')->where('viewing_ends_at', '>', $now)
            ->whereColumn('viewing_ends_at', '>', 'viewing_starts_at')->orderBy('viewing_starts_at')->get(['id', 'operator_id', 'code', 'first_name', 'last_name', 'room_id', 'viewing_starts_at', 'viewing_ends_at'])->groupBy('room_id');
        foreach (FuneralVehicle::where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $vehicles->keys()))->orderBy('plate')->get(['id', 'name', 'plate', 'is_active']) as $v) {
            $rows = [];
            foreach ($vehicles->get($v->id, collect()) as $b) {
                $rows[] = $this->booking('vehicle-'.$b->id, $b->funeralCase, $b->starts_at, $b->ends_at, $b->snapshot['director'] ?? null);
            }
            $resources[] = ['key' => 'vehicle-'.$v->id, 'kind' => 'vehicle', 'name' => $v->name, 'detail' => $v->plate, 'enabled' => $v->is_active, 'rows' => $rows];
        }
        foreach (Room::orderBy('name')->get(['id', 'name']) as $r) {
            $rows = [];
            foreach ($rooms->get($r->id, collect()) as $case) {
                $rows[] = $this->booking('room-'.$case->id, $case, $case->viewing_starts_at, $case->viewing_ends_at);
            }
            $resources[] = ['key' => 'room-'.$r->id, 'kind' => 'room', 'name' => $r->name, 'detail' => 'Camera ardente', 'enabled' => true, 'rows' => $rows];
        }

        return ['serverNow' => (int) round(microtime(true) * 1000), 'timezone' => config('app.timezone', 'Europe/Rome'), 'resources' => $resources];
    }

    public function realtimeConfig(): array
    {
        return ['enabled' => (bool) config('occupancy.enabled'), 'key' => (string) config('broadcasting.connections.reverb.key'),
            'host' => (string) config('occupancy.host'), 'port' => (int) config('occupancy.port'), 'tls' => config('occupancy.scheme') === 'https',
            'path' => config('occupancy.path'), 'authEndpoint' => '/broadcasting/auth'];
    }

    private function booking(string $key, FuneralCase $case, $start, $end, ?string $director = null): array
    {
        $canView = Gate::allows('view', $case);

        return ['key' => $key, 'start' => $start->getTimestamp() * 1000, 'end' => $end->getTimestamp() * 1000,
            'startLabel' => $start->format('d/m/Y H:i'), 'endLabel' => $end->format('d/m/Y H:i'),
            'label' => $canView ? $case->code.' · '.$case->full_name : 'Altra pratica', 'director' => $canView ? $director : null,
            'url' => $canView ? FuneralCaseResource::getUrl('edit',['record' => $case]) : null];
    }
}
