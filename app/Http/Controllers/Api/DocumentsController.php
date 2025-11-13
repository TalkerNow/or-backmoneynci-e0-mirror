<?php

namespace App\Http\Controllers\Api;

use App\Models\Documents;
use App\Models\User;
use App\Models\PersonalInformations;
use App\Models\Services;
use Illuminate\Http\Request;
use function MongoDB\BSON\toJSON;
use Log;

class DocumentsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function index()
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role == "admin" || $auth->role == "Consultant") {
            $doc = Documents::with(['user'])->get();
            $services = Services::all();
            $servtab = array();
            foreach ($doc as $do) {
                foreach ($services as $service) {
                    if ($do->id == $service->document_id) {
                        array_push($servtab, $service);
                    }
                }
                $do["services"] = $servtab;
                $servtab = array();
            }
        } else {
            $doc = Documents::with(['user'])->where('parent_id', $auth->id)->get();
        }
        return $doc->toJson(JSON_PRETTY_PRINT);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function create()
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        //        if ($auth->role != "admin")
//            return response()->json(['error' => 'Unauthorized'], 401);

        return view('create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    public function store(Request $request)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        //        if ($auth->role != "admin")
//            return response()->json(['error' => 'Unauthorized'], 401);
        $newdoc = Documents::create([
            'parent_id' => $request['parent_id'],
            'creator_id' => $request['creator_id'],
            'link_to_documents' => $request['link_to_documents'],
            'type' => $request['type'],
            'status_payment' => $request['status_payment'],
            'subscribe_services' => $request['subscribe_services'],
            'end_payment' => $request['end_payment'],
            'pre_payment' => $request['pre_payment'],
            'document_state' => $request['document_state'],
            'comment' => $request['comment'],
            'advanced_payment' => $request['advanced_payment'],
            'user_id' => $request['user_id'],
            'id' => $request['id'],
            'payment_method' => $request['payment_method'],
            'values' => $request['values'],
            'acompte_dates' => $request['acompte_dates'], // acompte
            'sold_dates'    => $request['sold_dates'],    // solde
        ]);

        if ($newdoc->type == "contrat") {
            $newdoc["services"] = $this->get_selected_services("template", $newdoc['id'], true);
            $newdoc["advanced_payment"] = $this->get_selected_total("template", $newdoc['id']);
        }
        // if user status is null, set the pending('En attente')
        $user = User::where('id', $request['user_id'])->get();
        if (count($user) > 0 && $user[0]->status == null) {
            User::where(['id' => $request['user_id']])
                ->limit(1)
                ->update(['status' => 'En attente']);
        }
        return $newdoc->toJSON(JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     *
     * @param Documents $docs
     * @return string
     */
    public function show($id)
    {
        $doc = Documents::find($id);
        $service = $this->get_services_by_doc($id);

        if ($doc == null) {
            return response()->json(['error' => 'Document does not exist'], 500);
        }
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        //        if ($auth->role != "admin" && $auth->id != $doc->user_id)
//            return response()->json(['error' => 'Unauthorized'], 401);

        $doc["services"] = $service;
        return $doc->toJSON(JSON_PRETTY_PRINT);
    }

    /**
     * Display the specified resource.
     *
     * @param Documents $docs
     * @return array|string
     */
    public function show_by_user($user_id)
    {
        $documents = Documents::with('user')->where('user_id', $user_id)->get();

        if ($documents === null) {
            return response()->json(['error' => 'Document does not exist'], 500);
        }
        return $documents;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\documents  $documents
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function edit(documents $document)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        //        if ($auth->role != "admin")
//            return response()->json(['error' => 'Unauthorized'], 401);
        return view('edit', compact('document'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\documents  $documents
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function update(Request $request, $id)
    {
        $doc = Documents::find($id);

        if ($doc == null)
            return response()->json(['error' => 'Document does not exist'], 500);

        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        $doc->update($request->all());
        //        if ($request->advanced_payment)
//            $doc->update(['advanced_payment' => $this->get_selected_total("selected", $doc->id)]);
        return "Document Updated !";
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\documents  $documents
     * @return \Illuminate\Http\JsonResponse|string
     */
    public function destroy($id)
    {
        $doc = Documents::find($id);

        if ($doc == null)
            return response()->json(['error' => 'Document does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        $this->delete_services($id);
        $doc->delete();
        return "Document Deleted !";
    }
    public function get_contract($id)
    {
        $result = Documents::where('documents.id', $id)
            ->join('users', 'documents.user_id', '=', 'users.id')
            ->join('personal_informations', 'users.id', '=', 'personal_informations.id')
            ->first();
        return response()->json(['data' => $result]);
    }
}
