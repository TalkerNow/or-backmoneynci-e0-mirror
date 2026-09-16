<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Controller;
use http\Env\Response;
use Illuminate\Http\Request;
use Log;

class LoginController extends Controller
{

    /**
     * Give a JWT if the email and password are good.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $creds = $request->only(['email', 'password']);

        Log::info($creds);
        if (!$token = auth()->attempt($creds)) {
            Log::info('ISSUE');
            return Response()->json(['error' => 'Incorrect email/password'], 401);
        }
        $authUser = Auth()->user();
        $name = $authUser->name;
        $role = $authUser->role;
        $email = $request->email;
        $id = $authUser->id;
        $permissions = $authUser->permissions()->pluck('permission');
        return response()->json(['accessToken' => $token, 'user' => ['email' => $email, 'id' => $id, 'name' => $name, 'role' => $role, 'permissions' => $permissions]]);
    }

    /**
     * Replace old JWT with a new one.
     *
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {

        try {
            $newToken = auth()->refresh();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        return response()->json(['token' => $newToken]);
    }

}
