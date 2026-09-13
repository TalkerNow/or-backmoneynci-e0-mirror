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
     * POST /api/inbound-emails — upsert by external_id (CF7 webhook) ou gmail_message_id (n8n).
     * Si OR_INGEST_KEY est défini : header X-OR-Ingest-Key obligatoire.
     * Si vide : POST public (n8n inchangé).
     */
    public function store(Request $request)
    {
        $ingestKey = (string) env('OR_INGEST_KEY', '');
        if ($ingestKey !== '') {
            $provided = (string) $request->header('X-OR-Ingest-Key', '');
            if (!hash_equals($ingestKey, $provided)) {
                return response()->json(['message' => 'Unauthorized ingest.'], 401);
            }
        }

        $data = $request->validate([
            'external_id'      => ['nullable', 'string', 'max:255'],
            'gmail_message_id' => ['nullable', 'string', 'max:255'],
            'gmail_thread_id'  => ['nullable', 'string', 'max:255'],
            'source'           => ['nullable', 'string', 'in:cf7,chatbot_report,other'],
            'from_email'       => ['nullable', 'string', 'max:255'],
            'from_name'        => ['nullable', 'string', 'max:255'],
            'to_email'         => ['nullable', 'string', 'max:255'],
            'subject'          => ['nullable', 'string', 'max:512'],
            'snippet'          => ['nullable', 'string'],
            'body'             => ['nullable', 'string'],
            'received_at'      => ['nullable', 'date'],
            'gmail_permalink'  => ['nullable', 'string', 'max:1024'],
        ]);

        $externalId = isset($data['external_id']) ? trim((string) $data['external_id']) : '';
        $gmailId = isset($data['gmail_message_id']) ? trim((string) $data['gmail_message_id']) : '';

        if ($externalId === '' && $gmailId === '') {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'external_id' => ['At least one of external_id or gmail_message_id is required.'],
                    'gmail_message_id' => ['At least one of external_id or gmail_message_id is required.'],
                ],
            ], 422);
        }

        if ($externalId === '') {
            unset($data['external_id']);
        } else {
            $data['external_id'] = $externalId;
        }
        if ($gmailId === '') {
            unset($data['gmail_message_id']);
        } else {
            $data['gmail_message_id'] = $gmailId;
        }

        $data['source'] = $data['source'] ?? 'other';

        // Upsert: priorité external_id (CF7), sinon gmail_message_id (n8n Gmail)
        if ($externalId !== '') {
            $row = InboundEmail::updateOrCreate(
                ['external_id' => $externalId],
                $data
            );
        } else {
            $row = InboundEmail::updateOrCreate(
                ['gmail_message_id' => $gmailId],
                $data
            );
        }

        $status = $row->wasRecentlyCreated ? 201 : 200;

        return response()->json($row, $status);
    }


    /**
     * GET /api/inbound-emails/unread-count?source=cf7 — badge Mails
     */
    public function unreadCount(Request $request)
    {
        $q = InboundEmail::query()->where(function ($w) {
            $w->where('is_read', false)->orWhereNull('is_read');
        });
        if ($request->filled('source')) {
            $sources = array_filter(array_map('trim', explode(',', (string) $request->get('source'))));
            if ($sources) {
                $q->whereIn('source', $sources);
            }
        }
        return response()->json(['count' => (int) $q->count()]);
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
