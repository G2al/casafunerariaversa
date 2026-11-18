<?php

namespace App\Policies;

use App\Models\ServiceOrder;
use App\Models\User;

class ServiceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isServiceOrderOperator();
    }

    public function view(User $user, ServiceOrder $order): bool
    {
        return $user->isAdmin() || ($user->isServiceOrderOperator() && $order->status !== 'draft');
    }

    public function update(User $user, ServiceOrder $order): bool
    {
        return $this->view($user, $order) && $order->status !== 'confirmed';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, ServiceOrder $order): bool
    {
        return false;
    }
}
