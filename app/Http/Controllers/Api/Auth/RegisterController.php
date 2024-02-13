<?php
namespace App\Http\Controllers\Api\Auth;

use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
use Log;

class RegisterController extends Controller
{

    public function register(Request $request)
    {
        $rules = [
            'name' => 'unique:users|required',
            'email' => 'unique:users|required',
            'password' => 'required',
        ];
        $input = $request->only('name', 'email', 'password');
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            Log::info("Error register");
            return response()->json(['success' => false, 'error' => $validator->messages()]);
        }
        $name = $request->name;
        $email = $request->email;
        $password = $request->password;
        $user = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
        if ($user === null) {
            Log::info("NON");
            return response()->json(['success' => false, 'error' => $validator->messages()]);
        }
        $user = User::where('email', $email);
        if (isset($request->role)) {
            $user->update(['role' => $request->role]);
        }
        if (isset($request->parent_id)) {
            $user->update(['parent_id' => $request->parent_id]);
        }
        $id = $user->first()->id;
        $creds = $request->only(['email', 'password']);
        $token = auth()->attempt($creds);
        return response()->json(['accessToken' => $token, 'user' => ['email' => $email, 'id' => $id, 'name' => $name]]);
    }
}
