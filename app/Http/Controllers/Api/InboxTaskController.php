<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboxTask;
use Illuminate\Http\Request;

class InboxTaskController extends Controller
{
    // GET /api/v1/inbox-tasks
    public function index(Request $request)
    {
        $q = InboxTask::query()->orderByDesc('id');

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('admin_id')) {
            $q->where('admin_id', $request->integer('admin_id'));
        }

        if ($request->filled('date')) {
            $q->whereDate('date', $request->date('date'));
        }

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }

    // GET /api/v1/inbox-tasks/{inboxTask}
    public function show(InboxTask $inboxTask)
    {
        return response()->json($inboxTask);
    }

    // POST /api/v1/inbox-tasks
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'  => ['nullable', 'integer'],
            'admin_id' => ['nullable', 'integer'],
            'date'     => ['nullable', 'date'],
            'data'     => ['nullable'], // Data libre (string, json, etc.)
        ]);

        $row = InboxTask::create($data);

        return response()->json($row, 201);
    }

    // PUT/PATCH /api/v1/inbox-tasks/{inboxTask}
    public function update(Request $request, InboxTask $inboxTask)
    {
        $request->validate([
            'user_id'  => ['nullable', 'integer'],
            'admin_id' => ['nullable', 'integer'],
            'date'     => ['nullable', 'date'],
            'data'     => ['nullable'],
        ]);

        $inboxTask->fill($request->only(['user_id', 'admin_id', 'date', 'data']))->save();

        return response()->json($inboxTask);
    }

    // DELETE /api/v1/inbox-tasks/{inboxTask}
    public function destroy(InboxTask $inboxTask)
    {
        $inboxTask->delete();

        return response()->json(null, 204);
    }
}
