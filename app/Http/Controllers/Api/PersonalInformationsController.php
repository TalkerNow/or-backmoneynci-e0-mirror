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
    public function index()
    {

    }

    public function store(Request $request)
    {
        $result = PersonalInformations::create($request->all());
        return response()->json([
            'user_id' => $request->id
        ]);
    }

    public function show($id)
    {

    }

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
        $request->validate([
            'civility' => 'nullable',
            'first_name' => 'nullable',
            'last_name' => 'nullable',
            'maiden' => 'nullable',
            'birth_date' => 'nullable',
            'martial_status' => 'nullable',
            'maiden_name' => 'nullable',
            'birth_place' => 'nullable',
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
            'business_introducer_id' => 'nullable',
            'secu_social' => 'nullable',
            'secu_social_key' => 'nullable',
        ]);
        $information->update($request->all());
    }

    public function destroy($id)
    {

    }
}
