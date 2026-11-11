<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class OccupancyChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public string $connection = 'database';

    public string $queue = 'realtime';

    public int $tries = 5;

    public int $timeout = 15;

    public array $backoff = [1, 2, 5, 10, 30];

    public function broadcastOn(): array
    {
        return [new PrivateChannel('occupancy')];
    }

    public function broadcastAs(): string
    {
        return 'occupancy.changed';
    }

    public function broadcastWith(): array
    {
        // Nessun nominativo, ID pratica, targa o dato personale sul canale condiviso.
        return ['refresh' => true];
    }

    public function broadcastConnections(): array
    {
        return ['reverb'];
    }
}
