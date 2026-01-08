<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallReport;
use Illuminate\Http\Request;

class CallReportController extends Controller
{
    // GET /api/v1/call-reports
    public function index(Request $request)
    {
        $q = CallReport::query()->orderByDesc('id');

        // filtres optionnels
        if ($request->filled('client_id')) {
            $q->where('client_id', $request->integer('client_id'));
        }
        if ($request->filled('admin_id')) {
            $q->where('admin_id', $request->integer('admin_id'));
        }

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }

    // GET /api/v1/call-reports/{call_report}
    public function show(CallReport $callReport)
    {
        return response()->json($callReport);
    }

    // POST /api/v1/call-reports
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'   => ['nullable', 'integer'],
            'admin_id'    => ['nullable', 'integer'],
            'call_report' => ['nullable', 'string'],
        ]);

        $row = CallReport::create($data);

        return response()->json($row, 201);
    }

    // PUT/PATCH /api/v1/call-reports/{call_report}
    public function update(Request $request, CallReport $callReport)
    {
        $request->validate([
            'client_id'   => ['nullable', 'integer'],
            'admin_id'    => ['nullable', 'integer'],
            'call_report' => ['nullable', 'string'],
        ]);

        // update partiel: ne modifie que ce qui est présent dans la requête
        $data = $request->only(['client_id', 'admin_id', 'call_report']);

        $callReport->fill($data)->save();

        return response()->json($callReport);
    }

    // DELETE /api/v1/call-reports/{call_report}
    public function destroy(CallReport $callReport)
    {
        $callReport->delete();

        return response()->json(null, 204);
    }
    public function getByClient(int $clientId, Request $request)
    {
        $q = \App\Models\CallReport::query()
            ->where('client_id', $clientId)
            ->orderByDesc('id');

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }

}
