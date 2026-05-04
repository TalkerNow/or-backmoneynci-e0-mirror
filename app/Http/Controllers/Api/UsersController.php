<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\PersonalInformations;
use App\Models\User;
use App\Models\OldClients;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Documents;
use App\Models\ConsultantHistory;
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
                    ->get(['users.*', 'personal_informations.first_name', 'personal_informations.last_name', 'personal_informations.civility', 'personal_informations.maiden_name', 'personal_informations.birth_date', 'personal_informations.birth_place', 'personal_informations.martial_status', 'personal_informations.children_number', 'personal_informations.mobile_number', 'personal_informations.office_number', 'personal_informations.personal_address', 'personal_informations.personal_address_2', 'personal_informations.personal_zip_code', 'personal_informations.personal_city', 'personal_informations.personal_country', 'personal_informations.society_name', 'personal_informations.society_address', 'personal_informations.society_address_2', 'personal_informations.society_zip_code', 'personal_informations.society_city', 'personal_informations.society_country', 'personal_informations.military_service', 'personal_informations.secu_social', 'personal_informations.secu_social_key']);
            } else {
                $users = User::with('parent')
                    ->with('business_introducer')
                    ->where('users.parent_id', $auth->id)
                    ->where('role', 'Client')
                    ->join('personal_informations', 'users.id', '=', 'personal_informations.id')
                    ->orderby('users.created_at', 'DESC')
                    ->get(['users.*', 'personal_informations.first_name', 'personal_informations.last_name', 'personal_informations.civility', 'personal_informations.maiden_name', 'personal_informations.birth_date', 'personal_informations.birth_place', 'personal_informations.martial_status', 'personal_informations.children_number', 'personal_informations.mobile_number', 'personal_informations.office_number', 'personal_informations.personal_address', 'personal_informations.personal_address_2', 'personal_informations.personal_zip_code', 'personal_informations.personal_city', 'personal_informations.personal_country', 'personal_informations.society_name', 'personal_informations.society_address', 'personal_informations.society_address_2', 'personal_informations.society_zip_code', 'personal_informations.society_city', 'personal_informations.society_country', 'personal_informations.military_service', 'personal_informations.secu_social', 'personal_informations.secu_social_key']);
            }
        }
        return response()->json($users);
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
            return response()->json($oldClient);
        } else {
            // Relations disponibles
            $availableRelations = [
                'parent',
                'business_introducer',
                'documents',
                'userKanbans.kanban',
                'conversationArchives',
                'simulatorDifficultyResults',
                'suiviAvancementsByUser.facture',
                'callReportsAsClient',
                'callReportsAsAdmin',
                'inboxTasksAsUser',
                'inboxTasksAsAdmin',
                'extractionDataRis',
                'files',
                'kpisAsAdmin',
                'simulatorErrorTagsAsUser',
                'simulatorErrorTagsAsAdmin',
                'tasksAsCreator',
                'tasksAsCustomer',
                'userFunds',
            ];

            $query = User::where('users.id', $id);

            // Si include est spécifié, charger uniquement ces relations
            if ($request->has('include')) {
                $includeParam = $request->input('include');
                
                // Si include=all, charger toutes les relations
                if ($includeParam === 'all') {
                    $query->with($availableRelations);
                } else {
                    $requestedRelations = explode(',', $includeParam);
                    $relationsToLoad = array_intersect($requestedRelations, $availableRelations);
                    
                    if (!empty($relationsToLoad)) {
                        $query->with($relationsToLoad);
                    }
                }
            }

            $user = $query->join('personal_informations', 'users.id', '=', 'personal_informations.id')
                ->first();

            if ($user === null) {
                return response()->json(['error' => 'User does not exist'], 500);
            }
            if ($auth->role != "admin" && $auth->id != $user->id && $auth->role != "Consultant" && $user->parent_id != $auth->id) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return response()->json($user);
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
                
                    // ✅ Vérifier si l'email est déjà utilisé par un autre utilisateur
                    if ($request->has('email')) {
                        $existing = User::where('email', $request->email)
                            ->where('id', '!=', $id)
                            ->first();
                    
                    if ($existing) {
                        return response()->json(['error' => 'Cet email est déjà utilisé'], 409);
                    }
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

                    if ($user['parent_id']) {
                        $oldParent = User::find($user['parent_id']);
                        $oldParentName = $oldParent
                            ? (($oldParent->first_name || $oldParent->last_name)
                                ? trim(($oldParent->first_name ?? '') . ' ' . ($oldParent->last_name ?? ''))
                                : $oldParent->name)
                            : null;
                        $request['previous_consultant_id'] = $user['parent_id'];
                        $request['previous_consultant_name'] = $oldParentName;
                        ConsultantHistory::create([
                            'user_id'         => $user->id,
                            'consultant_id'   => $user['parent_id'],
                            'consultant_name' => $oldParentName,
                        ]);
                    }
                }
            
                try {
                    $user->update($request->all());
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() === '23000') { // Duplicate entry
                        return response()->json(['error' => 'Cet email est déjà utilisé'], 409);
                    }
                    throw $e;
                }
            
            if (isset($request->p_password)) {
                $user->update(['password' => Hash::make($request->p_password)]);
            }
        }
    
        return response()->json(['success' => true, 'message' => 'Utilisateur mis à jour avec succès']);
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

    /**
     * Get all user information with all related data
     */
    public function getAllInformations(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $user = User::with([
            'parent',
            'business_introducer',
            'documents',
            'userKanbans.kanban',
            'conversationArchives',
            'simulatorDifficultyResults',
            'suiviAvancementsByUser.facture',
            'callReportsAsClient',
            'callReportsAsAdmin',
            'inboxTasksAsUser',
            'inboxTasksAsAdmin',
            'extractionDataRis',
            'files',
            'kpisAsAdmin',
            'simulatorErrorTagsAsUser',
            'simulatorErrorTagsAsAdmin',
            'tasksAsCreator',
            'tasksAsCustomer',
            'userFunds',
        ])->find($id);

        if (!$user) {
            return response()->json(['error' => 'User does not exist'], 404);
        }

        // Vérification des permissions
        if ($auth->role != "admin" && $auth->id != $user->id && $auth->role != "Consultant" && $user->parent_id != $auth->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json($user, 200);
    }

    public function consultantHistory(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $history = ConsultantHistory::where('user_id', $id)
            ->orderBy('changed_at', 'desc')
            ->get();

        return response()->json($history);
    }
}
