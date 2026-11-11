<?php

namespace App\Policies;

use App\Models\ManifestRecord;
use App\Models\User;

class ManifestRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, ManifestRecord $record): bool
    {
        return $this->viewAny($user) && ($user->isAdmin() || $record->operator_id === $user->id);
    }

    public function update(User $user, ManifestRecord $record): bool
    {
        return $this->view($user, $record);
    }
}
