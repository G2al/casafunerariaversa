<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

class ServiceOrderChanged extends OccupancyChanged
{
    public function __construct(public int $ownerId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('service-orders'), new PrivateChannel('service-orders.user.'.$this->ownerId)];
    }

    public function broadcastAs(): string
    {
        return 'service-order.changed';
    }
}
