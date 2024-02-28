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
use Log;


class UsersController extends Controller
{

    public function index(Request $request)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        $users = [];

        if ($request->kind === 'oldclient') {
            if ($auth->role === "admin" || $auth->role === "Consultant" || $auth->role === "Expert") {
                $users = OldClients::all();
            } else {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        } else if ($request->kind === 'member') {
            if ($auth->role === "admin" || $auth->role === "Consultant") {
                $users = User::where('role', '!=', 'Client')
                    ->join('personal_informations', 'users.id', '=', 'personal_informations.id')
                    ->orderby('users.created_at', 'DESC')
                    ->get();
            } else {
                $users = User::where('role', '!=', 'Client')
                    ->where('users.parent_id', $auth->id)
                    ->join('personal_informations', 'users.id', '=', 'personal_informations.id')
                    ->orderby('users.created_at', 'DESC')
                    ->get();
            }
        } else {
            if ($auth->role === "admin" || $auth->role === "Consultant") {
                $users = User::with('parent')
                    ->with('business_introducer')
                    ->where('role', 'Client')
                    ->join('personal_informations', 'users.id', '=', 'personal_informations.id')
                    ->orderby('users.created_at', 'DESC')
                    ->get();
            } else {
                $users = User::with('parent')
                    ->with('business_introducer')
                    ->where('users.parent_id', $auth->id)
                    ->where('role', 'Client')
                    ->join('personal_informations', 'users.id', '=', 'personal_informations.id')
                    ->orderby('users.created_at', 'DESC')
                    ->get();
            }
        }
        return $users->toJson(JSON_PRETTY_PRINT);
    }

    public function show(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($request->kind === 'oldclient') {
            $oldClient = OldClients::where('clcleunik', $id)->first();
            if ($oldClient === null) {
                return response()->json(['error' => 'User does not exist'], 500);
            }
            if ($auth->role != "admin" && $auth->id != $oldClient->clcleunik && $auth->role != "Consultant" && $user->parent_id != $auth->id) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return $oldClient->toJson(JSON_PRETTY_PRINT);
        } else {
            $user = User::where('users.id', $id)
                ->join('personal_informations', 'users.id', '=', 'personal_informations.id')
                ->first();
            if ($user === null) {
                return response()->json(['error' => 'User does not exist'], 500);
            }
            if ($auth->role != "admin" && $auth->id != $user->id && $auth->role != "Consultant" && $user->parent_id != $auth->id) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return $user->toJson(JSON_PRETTY_PRINT);
        }
    }
    // ? update information for an user, call by /api/users/id with PUT
    public function update(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($request->kind === 'oldclient') {
            $user = OldClients::where('clcleunik', $id)->first();
            if ($user === null) {
                return response()->json(['error' => 'User does not exist'], 404);
            }
            if ($request['parent_id'] !== $user['parent_id']) {
                DB::table('documents')
                    ->where('user_id', $user->clcleunik)
                    ->where('document_state', '!=', 'Termine')
                    ->update(['parent_id' => $request['parent_id']]);
            }
            $user->update($request->except(['updated_at']));
        } else {
            $user = $this->get_user($id);
            if ($user === null) {
                return response()->json(['error' => 'User does not exist'], 404);
            }
            $status = $request['status'];
            $status_fa = $request['status_fa'];
            if (!($user->status_fa === $status_fa && $user->status === $status)) {
                $request['status_update_date'] = date("Y-m-d");
            }
            if ($request['parent_id'] !== $user['parent_id']) {
                DB::table('documents')
                    ->where('user_id', $user->id)
                    ->where('document_state', '!=', 'Termine')
                    ->update(['parent_id' => $request['parent_id']]);
            }
            $user->update($request->all());
            if (isset($request->p_password)) {
                $user->update(['password' => Hash::make($request->p_password)]);
            }
        }


    }
    public function destroy(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $old = $request->query('old');

        if (isset($old)) {
            $oldClient = OldClients::where('clcleunik', $id)->first();
            if (isset($oldClient)) {
                $oldClient->delete();
            }
        } else {
            $user = $this->get_user($id);
            if ($user !== null) {
                $user->delete();
            }
            $personal_information = $this->get_personal_information($id);
            if ($personal_information !== null) {
                $personal_information->delete();
            }
        }

    }
    public function set_user_subscribe_services(Request $request)
    {
        User::where('id', $request->user_id)->limit(1)->update([
            'subscribe_services' => $request->subscribe_services
        ]);
        return true;
    }
    public function duplicated_email(Request $request)
    {
        $user = User::where('email', $request->email)->get();
        if (count($user) > 0)
            return "duplicated";
        else
            return "not duplicated";
    }
}
