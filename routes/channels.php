<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('occupancy', fn (User $user) => config('occupancy.enabled') && $user->is_active && $user->role !== null);
