<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = User::find(Auth::user()->id);
            if ($user->status !== 'active') {
                Auth::logout();

                return response()->json(['message' => 'La cuenta está desactivada.'], 403);
            }
            $token = $user->createToken('access_token')->accessToken;
            $data = [
                'user' => $user,
                'token' => $token,
            ];

            return response()->success($data, 200);
        } else {
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'Credenciales no válidas.',
            ], 401);
        }
    }

    // Register
    public function register(Request $request) {}

    // Recovery
    public function recovery(Request $request) {}

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->success([
            'message' => 'Successfully logged out',
        ]);
    }

    public function saveFCMToken(Request $request)
    {
        $user = Auth::user();
        $user->fcm_token = $request->input('fcmToken');
        $user->save();

        return response()->success([
            'message' => 'FCM token saved successfully',
        ], 200);
    }

    public function testFirebase()
    {
        $users = User::all();

        foreach ($users as $key => $user) {
            $role = $user->role;
            $user->roles()->attach($role->id);
        }

        return response()->success($users);
    }

    public function importFromEmployees()
    {
        $employees = Employee::all();
        if ($employees->isEmpty()) {
            return response()->json([
                'message' => 'No employees found',
            ], 404);
        }

        foreach ($employees as $employee) {
            $user = User::where('email', $employee->email)->first();
            if (! $user) {
                $user = new User;
                $user->name = $employee->name;
                $user->email = $employee->email;
                $user->phone = $employee->phone;
                $user->role_id = 3;
                $user->password = bcrypt('12345678');
                $user->save();
                $employee->user_id = $user->id;
                $employee->save();
            }
        }

        return response()->success($employees, 200);
    }
}
