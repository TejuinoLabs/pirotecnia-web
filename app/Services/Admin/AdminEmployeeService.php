<?php

namespace App\Services\Admin;

use App\Models\Employee;
use App\Models\User;
use App\Services\Inventory\AuditService;
use App\Services\Mobile\MobileContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminEmployeeService
{
    public function show(int $id): array
    {
        $row = Employee::with('user')->findOrFail($id);

        return ['id' => $row->id, 'user_id' => $row->user_id, 'name' => $row->name, 'email' => $row->email, 'phone' => $row->phone,
            'role_id' => $row->user?->role_id, 'experience_level_id' => $row->experience_level_id, 'status' => $row->status,
            'image_url' => app(AdminMediaService::class)->present($row->image_url)];
    }

    public function save(array $payload, User $actor, ?int $id = null): array
    {
        app(AdminAccess::class)->authorize($actor);

        return DB::transaction(function () use ($payload, $actor, $id) {
            $employee = $id ? Employee::whereKey($id)->lockForUpdate()->firstOrFail() : new Employee;
            $user = $employee->user_id ? User::whereKey($employee->user_id)->lockForUpdate()->firstOrFail() : new User;
            $required = $id && $user->exists ? 'sometimes' : 'required';
            $data = Validator::make($payload, [
                'name' => [$required, 'string', 'max:255'], 'email' => [$required, 'email', 'max:255', Rule::unique('users')->ignore($user->id), Rule::unique('employees')->ignore($id)],
                'phone' => [$required, 'string', 'max:40'], 'role_id' => [$required, 'integer', 'exists:roles,id'],
                'experience_level_id' => ['nullable', Rule::exists('experience_levels', 'id')->where('active', true)],
                'status' => ['sometimes', 'in:active,inactive,vacation'], 'password' => [$user->exists ? 'sometimes' : 'required', 'string', 'min:8', 'max:255'],
                'image_url' => ['nullable', 'url:http,https', 'max:4096'],
            ])->validate();
            app(AdminMediaService::class)->assertReference($data['image_url'] ?? null, 'image');
            $role = DB::table('roles')->where('id', $data['role_id'] ?? $user->role_id)->first();
            abort_unless($role && in_array($role->name, ['admin', 'user', 'technician'], true), 422, 'El rol debe ser administrador o personal operativo del catálogo real.');
            $root = app(AdminAccess::class)->isRoot($actor);
            abort_if(app(AdminAccess::class)->isRoot($user), 403, 'Una cuenta root no se administra como empleado.');
            if ($role->name === 'admin' || $user->role?->name === 'admin' || $user->roles()->where('name', 'admin')->exists()) {
                abort_unless($root, 403, 'Solo root puede crear o modificar personal administrador.');
            }
            MobileContract::conflict($user->id !== $actor->id || ((! isset($data['role_id']) || $data['role_id'] === $user->role_id) && ($data['status'] ?? 'active') === 'active'), 'No puedes desactivar tu propia cuenta ni cambiar su rol.', 'EMPLOYEE_SELF_CHANGE');
            if (($data['status'] ?? 'active') !== 'active') {
                $this->assertNotInCustody($employee->id, $user->id);
            }
            if ($user->exists && isset($data['role_id']) && (int) $data['role_id'] !== $user->role_id) {
                $this->assertNotInCustody($employee->id, $user->id);
            }
            $roleChanged = ! $user->exists || (isset($data['role_id']) && (int) $data['role_id'] !== $user->role_id);
            $user->fill(collect($data)->only(['name', 'email', 'phone', 'role_id', 'password'])->all());
            $user->forceFill(['status' => ($data['status'] ?? $employee->status ?? 'active') === 'inactive' ? 'inactive' : 'active'])->save();
            if ($roleChanged) {
                $user->roles()->sync([$user->role_id]);
            } else {
                $user->roles()->syncWithoutDetaching([$user->role_id]);
            }
            $employee->forceFill(collect($data)->except(['password', 'role_id'])->all() + ['user_id' => $user->id, 'salary' => $employee->salary ?? '0'])->save();
            if ($user->status === 'inactive') {
                $tokens = DB::table('oauth_access_tokens')->where('user_id', $user->id)->pluck('id');
                DB::table('oauth_access_tokens')->whereIn('id', $tokens)->update(['revoked' => true]);
                DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokens)->update(['revoked' => true]);
                if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }
            }
            app(AuditService::class)->record($actor->id, $id ? 'employee_updated' : 'employee_created', 'employee', $employee->id, collect($data)->except('password')->all());

            return $this->show($employee->id);
        });
    }

    public function archive(int $id, User $actor): array
    {
        app(AdminAccess::class)->authorize($actor);

        return DB::transaction(function () use ($id, $actor) {
            $employee = Employee::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($employee->user_id) {
                return $this->save(['status' => 'inactive'], $actor, $id);
            }
            $this->assertNotInCustody($id, null);
            $employee->forceFill(['status' => 'inactive'])->save();
            app(AuditService::class)->record($actor->id, 'employee_archived', 'employee', $id, ['status' => 'inactive']);

            return $this->show($id);
        });
    }

    private function assertNotInCustody(?int $employee, ?int $user): void
    {
        if ($user) {
            MobileContract::conflict(! DB::table('event_dispatches')->where('technician_id', $user)->whereNotIn('status', ['closed', 'cancelled'])->exists(), 'El empleado tiene entregas o custodia pendiente.', 'EMPLOYEE_HAS_CUSTODY');
        }
        MobileContract::conflict(! DB::table('employee_event')->join('events', 'events.id', '=', 'event_id')->where('employee_id', $employee)->whereNotIn('events.status', ['closed', 'cancelled'])->where('event_date', '>=', now(config('mobile.event_timezone'))->startOfDay())->exists(), 'Reasigna sus eventos activos antes de desactivarlo.', 'EMPLOYEE_ASSIGNED');
    }
}
