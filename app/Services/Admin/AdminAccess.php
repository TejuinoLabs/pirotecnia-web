<?php

namespace App\Services\Admin;

use App\Models\User;

class AdminAccess
{
    public function isRoot(User $user): bool
    {
        return $user->role?->name === 'root' || $user->roles()->where('name', 'root')->exists();
    }

    public function authorize(User $user): void
    {
        abort_unless($user->status === 'active' && ($this->isRoot($user) || $user->role?->name === 'admin' || $user->roles()->where('name', 'admin')->exists()), 403, 'Se requiere una cuenta administrativa activa.');
    }
}
