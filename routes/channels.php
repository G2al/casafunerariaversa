<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('occupancy', fn (User $user) => config('occupancy.enabled') && $user->is_active && $user->role !== null && ! $user->isServiceOrderOperator());

Broadcast::channel('service-orders', fn (User $user) => config('occupancy.enabled') && ($user->isAdmin() || $user->isServiceOrderOperator()));
Broadcast::channel('service-orders.user.{id}', fn (User $user, $id) => config('occupancy.enabled') && $user->is_active && ! $user->isServiceOrderOperator() && $user->id === (int) $id);
