<?php
namespace App\Http\Controllers\Api\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Api\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
class RegisterController extends Controller
{
    /**
     * Create an account and add it in the database, also gives you a JWT.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $rules = [
            'name' => 'unique:users|required',
            'email'    => 'unique:users|required',
            'password' => 'required',
        ];
        $input     = $request->only('name', 'email','password');
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()]);
        }
        $name = $request->name;
        $email    = $request->email;
        $password = $request->password;
        $user     = User::create(['name' => $name, 'email' => $email, 'p_password' =>$password,'password' => Hash::make($password), 'role' =>$request->role,'parent_id' =>$request->parent_id]);
        $id = $user->id;
        $creds = $request->only(['email', 'password']);
        $token = auth()->attempt($creds);
        return response()->json(['accessToken' => $token, 'user' => ['email' => $email, 'id' => $id, 'name' => $name]]);
    }
}
