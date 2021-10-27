<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\PersonalInformations;
use App\Models\User;
use App\Models\OldClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
//        if ($auth->role != "admin")
//            return response()->json(['error' => 'Unauthorized'], 401);
        if ($request->kind == 'oldclient'){
            if($auth->role == "admin" || $auth->role == "Consultant"){
                $oldclient = OldClient::all();
            }else{
                $users = User::with('parent')->where(function ($query) {
                    $query->where('role','Client');
                })->where('parent_id', $auth->id)->orderby('created_at','DESC')->get();
                $infos = PersonalInformations::where('parent_id', $auth->id)->get();
            } 
        }
        else if($request->kind == 'client'){
            if($auth->role == "admin" || $auth->role == "Consultant"){
                $users = User::with('parent')->where('role','Client')->orderby('created_at','DESC')->get();
                $infos = PersonalInformations::all();
            }else{
                $users = User::with('parent')->where(function ($query) {
                    $query->where('role','Client');
                })->where('parent_id', $auth->id)->orderby('created_at','DESC')->get();
                $infos = PersonalInformations::where('parent_id', $auth->id)->get();
            }
        }else if($request->kind == 'member'){
            if($auth->role == "admin" || $auth->role == "Consultant"){
                $users = User::where('role','!=','Client')->orderby('created_at','DESC')->get();
                $infos = PersonalInformations::all();
            }else{
                $users = User::where(function ($query) {
                    $query->where('role','!=','Client');
                })->where('parent_id', $auth->id)->orderby('created_at','DESC')->get();
                $infos = PersonalInformations::where('parent_id', $auth->id)->get();
            }
        }

        foreach ($users as $user) {
            foreach ($infos as $info) {
                if ($user->id == $info->id) {
                    $user["personal_informations"] = $info;
                }
            }
        }
        if ($request->kind == 'client' || $request->kind == 'member'){
            return $users->toJson(JSON_PRETTY_PRINT);
        }else if ($request->kind == 'oldclient'){
            return $oldclient->toJson(JSON_PRETTY_PRINT);
        }
    }

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
        $status = $request['status'];
        $status_fa = $request['status_fa'];
        if(!($user->status_fa==$status_fa && $user->status==$status)) {
            $request['status_update_date']= date("Y-m-d");
        }
        $user->update($request->all());
        if(isset($request->p_password))
            $user->update(['password'=>Hash::make($request->p_password)]);
    }

    public function destroy($id)
    {
        $user = $this->get_user($id);
        if ($user != null)
            $user->delete();

        $personal_information = $this->get_personal_information($id);
        if ($personal_information != null)
            $personal_information->delete();
    }
    public function set_user_subscribe_services(Request $request){
        User::where('id', $request->user_id)->limit(1)->update([
            'subscribe_services' => $request->subscribe_services]);
        return true;
    }
    public function duplicated_email(Request $request){
        $user = User::where('email', $request->email)->get();
        if(count($user) > 0)
            return "duplicated";
        else
            return "not duplicated";
    }
}
