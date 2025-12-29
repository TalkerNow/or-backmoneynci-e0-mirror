<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimulatorErrorTag;
use Illuminate\Http\Request;

class SimulatorErrorTagController extends Controller
{
    // GET /v1/simulator-error-tags
    public function index(Request $request)
    {
        $q = SimulatorErrorTag::query()->orderByDesc('id');

        if ($request->filled('admin_id')) {
            $q->where('admin_id', $request->integer('admin_id'));
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('document_id')) {
            $q->where('document_id', $request->integer('document_id'));
        }

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }

    // GET /v1/simulator-error-tags/{simulator_error_tag}
    public function show(SimulatorErrorTag $simulatorErrorTag)
    {
        return response()->json($simulatorErrorTag);
    }

    // POST /v1/simulator-error-tags
    public function store(Request $request)
    {
        $data = $request->validate([
            'admin_id' => ['nullable','integer'],
            'user_id' => ['nullable','integer'],
            'document_id' => ['nullable','integer'],
            'error_handling' => ['nullable','string'],
            'tag' => ['nullable','array'],
            'tag.*' => ['nullable','string'],
        ]);

        $row = SimulatorErrorTag::create($data);

        return response()->json($row, 201);
    }

    // PUT/PATCH /v1/simulator-error-tags/{simulator_error_tag}
    public function update(Request $request, SimulatorErrorTag $simulatorErrorTag)
    {
        $request->validate([
            'admin_id' => ['nullable','integer'],
            'user_id' => ['nullable','integer'],
            'document_id' => ['nullable','integer'],
            'error_handling' => ['nullable','string'],
            'tag' => ['nullable','array'],
            'tag.*' => ['nullable','string'],
        ]);

        // update partiel: on ne touche qu’aux champs présents dans la requête
        $allowed = ['admin_id','user_id','document_id','error_handling','tag'];
        $data = $request->only($allowed);

        $simulatorErrorTag->fill($data)->save();

        return response()->json($simulatorErrorTag);
    }

    // DELETE /v1/simulator-error-tags/{simulator_error_tag}
    public function destroy(SimulatorErrorTag $simulatorErrorTag)
    {
        $simulatorErrorTag->delete();
        return response()->json(null, 204);
    }

    // GET /v1/simulator-error-tags/client/{clientId}
    // (clientId == user_id)
    public function getByClient(int $clientId, Request $request)
    {
        $q = SimulatorErrorTag::query()
            ->where('user_id', $clientId)
            ->orderByDesc('id');

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }

    // GET /v1/simulator-error-tags/document/{documentId}
    public function getByDocument(int $documentId, Request $request)
    {
        $q = SimulatorErrorTag::query()
            ->where('document_id', $documentId)
            ->orderByDesc('id');

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }
}
