<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\PersonalInformations;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
//        if ($auth->role != "admin")
//            return response()->json(['error' => 'Unauthorized'], 401);

        if($auth->role == "admin"){
            $users = User::all();
            $infos = PersonalInformations::all();
        }else{
            $users = User::where('parent_id', $auth->id)->get();
            $infos = PersonalInformations::where('parent_id', $auth->id)->get();
        }

        foreach ($users as $user) {
            foreach ($infos as $info) {
                if ($user->id == $info->id) {
                    $user["personal_informations"] = $info;
                }
            }
        }
        return $users->toJson(JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $info = $this->get_personal_information($id);
        $user = $this->get_user($id);

        if ($user == null)
            return response()->json(['error' => 'User does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
//        if ($auth->role != "admin" && $auth->id != $user->id)
//            return response()->json(['error' => 'Unauthorized'], 401);

        $user["personal_informations"] = $info;
        return $user->toJson(JSON_PRETTY_PRINT);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $user = $this->get_user($id);

        if ($user == null)
            return response()->json(['error' => 'User does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
//        if ($auth->role != "admin" && $auth->id != $user->id)
//            return response()->json(['error' => 'Unauthorized'], 401);
        $user->update($request->all());
        if(isset($request->p_password))
            $user->update(['password'=>Hash::make($request->p_password)]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function destroy($id)
    {
        $user = $this->get_user($id);
        $personal_information = $this->get_personal_information($id);

        if ($user == null)
            return response()->json(['error' => 'User does not exist'], 500);
        if ($personal_information == null)
            return response()->json(['error' => 'User does not exist'], 500);

        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
//        if ($auth->role != "admin")
//            return response()->json(['error' => 'Unauthorized'], 401);

        $personal_information->delete();
        $user->delete();
    }
    public function set_user_subscribe_services(Request $request){
        User::where('id', $request->user_id)->limit(1)->update([
            'subscribe_services' => $request->subscribe_services]);
        return true;
    }
}
