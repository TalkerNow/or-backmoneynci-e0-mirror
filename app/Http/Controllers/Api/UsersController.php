<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\PersonalInformations;
use App\Models\User;
use App\Models\OldClients;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Documents;
use DB;


class UsersController extends Controller
{

    public function index(Request $request)
    {
        
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($request->kind == 'oldclient'){
            if($auth->role == "admin" || $auth->role == "Consultant" || $auth->role == "Expert"){
                $oldclients = OldClients::all();
                return response()->json(['data' => $oldclients], 200); 
                
                return $oldclients->toJson(JSON_PRETTY_PRINT);
            }else {
                return response()->json(['error' => 'Unauthorized'], 401);
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
            }else {
                $users = User::where(function ($query) {
                    $query->where('role','!=','Client');
                })->where('parent_id', $auth->id)->orderby('created_at','DESC')->get();
                $infos = PersonalInformations::where('parent_id', $auth->id)->get();
            }
        }
        if ($request->kind == 'client' || $request->kind == 'member') {
            foreach ($users as $user) {
                foreach ($infos as $info) {
                    if ($user->id == $info->id) {
                        $user["personal_informations"] = $info;
                    }
                }
            }
            return $users->toJson(JSON_PRETTY_PRINT);
        } else if ($request->kind == 'oldclient'){ 
            return $oldclients->toJson(JSON_PRETTY_PRINT);
        } else
            return response()->json(['error' => Unauthorized], 401);
    }

    public function show($id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $info = $this->get_personal_information($id);
        $user = $this->get_user($id);

        if ($user == null)
            return response()->json(['error' => 'User does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin" && $auth->id != $user->id && $auth->role != "Consultant" && $user->parent_id != $auth->id)
            return response()->json(['error' => 'Unauthorized'], 401);

        $user["personal_informations"] = $info;
        return $user->toJson(JSON_PRETTY_PRINT);
    }
   // ? update information for an user, call by /api/users/id with PUT
    public function update(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        $user = $this->get_user($id);
        if ($user == null)
            return response()->json(['error' => 'User does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        $status = $request['status'];
        $status_fa = $request['status_fa'];
        if(!($user->status_fa === $status_fa && $user->status === $status)) {
            $request['status_update_date'] = date("Y-m-d");
        }
        if ($request['parent_id'] !== $user['parent_id']) {
            // TODO pour tous les contrats en attent ou en cours, si le parent_id change, il faut changer le parent id dans les contrat
            $documents = DB::table('documents')
            ->where('user_id', $user->id)
            ->where('parent_id', $user->parent_id)
            ->where('document_state', '!=', 'Termine')
            ->get();
            // $documents->parent_id=$request['parent_id'];
            // $documents->save(); 
            return json_encode($documents);
        }
        
        $user->update($request->all());
        if(isset($request->p_password))
            $user->update(['password'=>Hash::make($request->p_password)]);
    }
    public function destroy($id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

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
