<?php

namespace App\Services\Inventory;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryAccess
{
    public function allows(User $user, string $permission): bool
    {
        if ($user->status !== 'active') {
            return false;
        }
        $roles = $user->roles()->pluck('name')->merge([$user->role?->name])->filter()->all();
        if (array_intersect($roles, ['root', 'admin'])) {
            return true;
        }
        if (DB::table('inventory_permissions')->where('user_id', $user->id)->where('permission', $permission)->exists()) {
            return true;
        }
        $defaults = config('inventory.role_permissions', []);
        foreach ($roles as $role) {
            if (in_array($permission, $defaults[$role] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public function authorize(User $user, string $permission): void
    {
        abort_unless($this->allows($user, $permission), 403, 'No tienes permiso para esta operación.');
    }
}
