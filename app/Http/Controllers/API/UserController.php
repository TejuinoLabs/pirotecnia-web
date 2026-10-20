<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();

        return ['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role?->name]];
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'confirmed', Password::min(8)]]);
        DB::transaction(function () use ($request, $data) {
            $user = \App\Models\User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.']);
            }
            $user->password = $data['password'];
            $user->remember_token = null;
            $user->save();
            // Keep this device signed in, revoke other Passport sessions and refresh tokens.
            $token = $request->user()->token();
            $currentToken = $token instanceof \Laravel\Passport\AccessToken ? $token->oauth_access_token_id : null;
            if (\Illuminate\Support\Facades\Schema::hasTable('oauth_access_tokens')) {
                $tokens = DB::table('oauth_access_tokens')->where('user_id', $user->id)->when($currentToken, fn ($q) => $q->where('id', '!=', $currentToken))->pluck('id');
                DB::table('oauth_access_tokens')->whereIn('id', $tokens)->update(['revoked' => true]);
                if (\Illuminate\Support\Facades\Schema::hasTable('oauth_refresh_tokens')) {
                    DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokens)->update(['revoked' => true]);
                }
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });

        return ['message' => 'Contraseña actualizada'];
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)]]);
        $user = $request->user();
        if ($user->email !== $data['email']) {
            $user->email_verified_at = null;
        }
        $user->fill($data)->save();

        return $this->profile($request);
    }
}
