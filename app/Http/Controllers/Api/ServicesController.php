<?php

namespace App\Http\Controllers\Api;

use App\Models\Documents;
use App\Models\Services;
use Illuminate\Http\Request;
use function Faker\Provider\pt_BR\check_digit;

class ServicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|string
     */
    public function index()
    {
        $services = Services::all();

        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin")
            return response()->json(['error' => 'Unauthorized'], 401);

        return $services->toJson(JSON_PRETTY_PRINT);
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
        if ($auth->role != "admin")
            return response()->json(['error' => 'Unauthorized'], 401);

        return view('create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin")
            return response()->json(['error' => 'Unauthorized'], 401);

        return Services::create([
            'name' => $request['name'],
            'description' => $request['description'],
            'variable' => $request['variable'],
            'value' => $request['value'],
            'variable1' => $request['variable1'],
            'value1' => $request['value1'],
            'total_ht' => $request['total_ht'],
            'total_ttc' => $request['total_ttc'],
            'tva' => $request['tva'],
            'id' => $request['id'],
            'status' => $request['status'],
            'document_id' => $request['document_id'],
            'parent_id' => $request['parent_id']
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Services  $services
     * @return Services|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function show($id)
    {
        $service = Services::find($id);

        if ($service == null)
            return response()->json(['error' => 'Service does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin")
            return response()->json(['error' => 'Unauthorized'], 401);

        return $service;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Services  $services
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function show_by_status($status)
    {
        if (is_numeric($status))
            return ($this->show($status));
        $service = $this->get_selected_services($status, 0, false);

        if ($service == null)
            return response()->json(['error' => 'Service does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin")
            return response()->json(['error' => 'Unauthorized'], 401);

        return $service;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Services  $services
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function edit(Services $services)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin")
            return response()->json(['error' => 'Unauthorized'], 401);

        return view('edit', compact('services'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Services  $services
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|string
     */
    public function update(Request $request, $id)
    {
        $service = Services::find($id);


        if ($service == null)
            return response()->json(['error' => 'Service does not exist'], 500);

        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin")
            return response()->json(['error' => 'Unauthorized'], 401);

        $doc = Documents::find($service->document_id);

        if ($request->tva)
            $service->update(['tva' => $request->tva]);

        if ($request->total_ttc)
            $service->total_ht = $request->total_ttc - ($request->total_ttc * ($service->tva / 100));
        else if ($request->total_ht)
            $service->total_ttc = $request->total_ht + ($request->total_ht * ($service->tva / 100));

        if ($doc && ($request->total_ttc || $request->total_ht))
            $doc["advanced_payment"] = $this->get_selected_total("selected", $doc['id']);

        $service->update($request->all());
        return "Service Updated !";
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Services  $services
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $service = Services::find($id);

        if ($service == null)
            return response()->json(['error' => 'Service does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin")
            return response()->json(['error' => 'Unauthorized'], 401);

        $service->delete();
    }
}