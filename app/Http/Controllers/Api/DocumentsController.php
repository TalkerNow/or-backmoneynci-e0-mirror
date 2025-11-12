<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        // if ($auth->role != "admin") return response()->json(['error' => 'Unauthorized'], 401);

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
        // if ($auth->role != "admin") return response()->json(['error' => 'Unauthorized'], 401);

        $newdoc = Documents::create([
            'parent_id'          => $request->input('parent_id'),
            'creator_id'         => $request->input('creator_id'),
            'link_to_documents'  => $request->input('link_to_documents'),
            'type'               => $request->input('type'),
            'status_payment'     => $request->input('status_payment'),
            'subscribe_services' => $request->input('subscribe_services'),
            'end_payment'        => $request->input('end_payment'),
            'pre_payment'        => $request->input('pre_payment'),
            'document_state'     => $request->input('document_state'),
            'comment'            => $request->input('comment'),
            'advanced_payment'   => $request->input('advanced_payment'),
            'user_id'            => $request->input('user_id'),
            'id'                 => $request->input('id'), // si tu tiens à setter l'id manuellement
            'payment_method'     => $request->input('payment_method'),
            'values'             => $request->input('values'),

            // IMPORTANT : toujours envoyer une valeur non nulle
            'sold_dates'         => $this->normalizeJsonText($request->input('sold_dates', '[]')),
            'acompte_dates'      => $this->normalizeJsonText($request->input('acompte_dates', '[]')),
        ]);

        if ($newdoc->type == "contrat") {
            $newdoc["services"] = $this->get_selected_services("template", $newdoc['id'], true);
            $newdoc["advanced_payment"] = $this->get_selected_total("template", $newdoc['id']);
        }

        // if user status is null, set pending ('En attente')
        $user = User::where('id', $request->input('user_id'))->first();
        if ($user && $user->status === null) {
            $user->update(['status' => 'En attente']);
        }

        return $newdoc->toJson(JSON_PRETTY_PRINT);
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
        // if ($auth->role != "admin" && $auth->id != $doc->user_id) return response()->json(['error' => 'Unauthorized'], 401);

        $doc["services"] = $service;
        return $doc->toJson(JSON_PRETTY_PRINT);
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
        // if ($auth->role != "admin") return response()->json(['error' => 'Unauthorized'], 401);
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

        if ($doc == null) {
            return response()->json(['error' => 'Document does not exist'], 500);
        }

        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $data = $request->all();

        // Ne pas écraser par NULL ; normaliser si fourni
        if (!array_key_exists('sold_dates', $data) || $data['sold_dates'] === null) {
            $data['sold_dates'] = $doc->sold_dates ?? '[]';
        } else {
            $data['sold_dates'] = $this->normalizeJsonText($data['sold_dates']);
        }

        if (!array_key_exists('acompte_dates', $data) || $data['acompte_dates'] === null) {
            $data['acompte_dates'] = $doc->acompte_dates ?? '[]';
        } else {
            $data['acompte_dates'] = $this->normalizeJsonText($data['acompte_dates']);
        }

        $doc->update($data);

        // if ($request->advanced_payment) $doc->update(['advanced_payment' => $this->get_selected_total("selected", $doc->id)]);
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

        if ($doc == null) {
            return response()->json(['error' => 'Document does not exist'], 500);
        }

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

    /**
     * Normalise un champ JSON (accepte array|string|null) en texte JSON.
     * - array => json_encode()
     * - string non vide => renvoyé tel quel
     * - null / string vide => '[]'
     */
    private function normalizeJsonText($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }
        return '[]';
    }
}
