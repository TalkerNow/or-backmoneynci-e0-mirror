<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\PersonalInformations;
use App\Models\User;
use Dotenv\Validator;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Types\Nullable;

class PersonalInformationsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return PersonalInformations::create($request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
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
        $information = $this->get_personal_information($id);

        if ($information == null)
            return response()->json(['error' => 'User does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        if ($auth->role != "admin" && $auth->id != $user->id)
            return response()->json(['error' => 'Unauthorized'], 401);

        $request->validate([
            'civility' => 'nullable',
            'first_name' => 'nullable',
            'last_name' => 'nullable',
            'maiden' => 'nullable',
            'birth_date' => 'nullable',
            'martial_status' => 'nullable',
            'children_number' => 'nullable',
            'mobile_number' => 'nullable',
            'office_number' => 'nullable',
            'personal_address' => 'nullable',
            'personal_address_2' => 'nullable',
            'personal_zip_code' => 'nullable',
            'personal_city' => 'nullable',
            'personal_country' => 'nullable',
            'society_name' => 'nullable',
            'society_address' => 'nullable',
            'society_address_2' => 'nullable',
            'society_zip_code' => 'nullable',
            'society_city' => 'nullable',
            'society_country' => 'nullable',
            'notes' => 'nullable',
        ]);
        $information->update($request->all());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

    }
}
