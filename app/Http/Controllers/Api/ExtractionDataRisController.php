<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtractionDataRis;
use Illuminate\Http\Request;

class ExtractionDataRisController extends Controller
{
    // GET /api/v1/extraction-data-ris
    public function index(Request $request)
    {
        $q = ExtractionDataRis::query()->orderByDesc('id');

        if ($request->filled('user_id')) {
$q->where('user_id', (int) $request->query('user_id'));
        }

        if ($request->filled('nir')) {
            $nir = preg_replace('/\s+/', '', (string) $request->query('nir'));
            $q->where('nir', $nir);
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $q->where('text', 'like', '%' . $search . '%');
        }

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }

    // GET /api/v1/extraction-data-ris/{extraction_data_ri}
    public function show(ExtractionDataRis $extractionDataRi)
    {
        return response()->json($extractionDataRi);
    }

    // POST /api/v1/extraction-data-ris
    public function store(Request $request)
    {
        // Supporte format n8n: [ { ... } ]
        $payload = $request->all();
        if (is_array($payload) && array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
            $payload = $payload[0];
        }

        $data = validator($payload, [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'text'    => ['required', 'string'],
            'nir'     => ['nullable', 'string', 'max:32'],
        ])->validate();

        $row = ExtractionDataRis::create($data);

        return response()->json($row, 201);
    }

    // PUT/PATCH /api/v1/extraction-data-ris/{extraction_data_ri}
    public function update(Request $request, ExtractionDataRis $extractionDataRi)
    {
        $request->validate([
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'text'    => ['sometimes', 'required', 'string'],
            'nir'     => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        $data = $request->only(['user_id', 'text', 'nir']);
        $extractionDataRi->fill($data)->save();

        return response()->json($extractionDataRi);
    }

    // DELETE /api/v1/extraction-data-ris/{extraction_data_ri}
    public function destroy(ExtractionDataRis $extractionDataRi)
    {
        $extractionDataRi->delete();

        return response()->json(null, 204);
    }
}
