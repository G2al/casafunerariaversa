<?php

namespace App\Policies;

use App\Models\FuneralCase;
use App\Models\User;

class FuneralCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, FuneralCase $case): bool
    {
        return $this->viewAny($user) && ($user->isAdmin() || $case->operator_id === $user->id);
    }

    public function update(User $user, FuneralCase $case): bool
    {
        return $this->view($user, $case) && (! $case->trashed() || $user->isAdmin());
    }

    public function delete(User $user, FuneralCase $case): bool
    {
        return $this->view($user, $case);
    }

    public function restore(User $user, FuneralCase $case): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, FuneralCase $case): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
