<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboundEmail;
use Illuminate\Http\Request;

class InboundEmailController extends Controller
{
    /**
     * GET /api/inbound-emails — list (dashboard / inbox later)
     * Same public pattern as conversation-archives for now; harden auth later if needed.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 50);
        $perPage = $perPage > 200 ? 200 : max(1, $perPage);

        $q = InboundEmail::query()->orderByDesc('received_at')->orderByDesc('id');

        if ($request->filled('source')) {
            $sources = array_filter(array_map('trim', explode(',', (string) $request->get('source'))));
            if ($sources) {
                $q->whereIn('source', $sources);
            }
        }

        if ($request->filled('from')) {
            $from = $request->get('from');
            $q->where('received_at', '>=', $from);
        }
        if ($request->filled('to')) {
            $to = $request->get('to');
            $q->where('received_at', '<=', $to);
        }

        return response()->json($q->paginate($perPage));
    }

    /**
     * POST /api/inbound-emails — upsert by gmail_message_id (n8n Gmail OAuth later)
     * Public store pattern = conversation-archives (n8n posts without JWT).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'gmail_message_id' => ['required', 'string', 'max:255'],
            'gmail_thread_id'  => ['nullable', 'string', 'max:255'],
            'source'           => ['nullable', 'string', 'in:cf7,chatbot_report,other'],
            'from_email'       => ['nullable', 'string', 'max:255'],
            'from_name'        => ['nullable', 'string', 'max:255'],
            'to_email'         => ['nullable', 'string', 'max:255'],
            'subject'          => ['nullable', 'string', 'max:512'],
            'snippet'          => ['nullable', 'string'],
            'received_at'      => ['nullable', 'date'],
            'gmail_permalink'  => ['nullable', 'string', 'max:1024'],
        ]);

        $data['source'] = $data['source'] ?? 'other';

        $row = InboundEmail::updateOrCreate(
            ['gmail_message_id' => $data['gmail_message_id']],
            $data
        );

        $status = $row->wasRecentlyCreated ? 201 : 200;

        return response()->json($row, $status);
    }

    public function show(int $id)
    {
        $row = InboundEmail::find($id);
        if (!$row) {
            return response()->json(['message' => 'Inbound email introuvable.'], 404);
        }
        return response()->json($row);
    }
}
