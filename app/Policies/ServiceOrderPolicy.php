<?php

namespace App\Policies;

use App\Models\FuneralCase;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

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

    public function viewPhoto(User $user, ServiceOrder $order): bool
    {
        if ($this->view($user, $order)) {
            return true;
        }
        $case = $order->funeral_case_id ? FuneralCase::withTrashed()->find($order->funeral_case_id) : null;

        return $case && Gate::forUser($user)->allows('view', $case);
    }

    public function delete(User $user, ServiceOrder $order): bool
    {
        return $user->isAdmin();
    }
}
