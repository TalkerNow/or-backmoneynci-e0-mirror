<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\ContractTemplates;
use App\Models\User;
use Dotenv\Validator;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Types\Nullable;

class ContractTemplateController extends Controller
{

    public function index()
    {

    }
    public function get_template($id)
    {
        $result = ContractTemplates::find($id);
        return response()->json($result);
    }

    public function store(Request $request)
    {
        return ContractTemplates::create($request->all());
    }

    public function show($id)
    {
    }
    public function update(Request $request, $id)
    {
        $template = ContractTemplates::find($id);

        if ($template == null)
            return response()->json(['error' => 'Template does not exist'], 500);

        $template->update($request->all());
        return "Template Updated !";
    }

    public function destroy($id)
    {

    }
}
