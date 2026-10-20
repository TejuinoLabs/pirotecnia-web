<?php

namespace App\Presentation\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
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
}
