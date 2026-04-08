<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserKanban;
use Illuminate\Http\Request;

class UserKanbanController extends Controller
{
    /**
     * Display a listing of user kanban cards.
     */
    public function index(Request $request)
    {
        $query = UserKanban::with(['user', 'kanban']);

        // Filtrer par kanban_id si fourni
        if ($request->has('kanban_id')) {
            $query->where('kanban_id', $request->kanban_id);
        }

        // Filtrer par user_id si fourni
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtrer par date si fourni
        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // Filtrer par status si fourni
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $userKanbans = $query->orderBy('date')->get();

        return response()->json($userKanbans);
    }

    /**
     * Store a newly created user kanban card.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'kanban_id' => 'required|exists:kanbans,id',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'hour' => 'nullable|date_format:H:i',
            'status' => 'nullable|string|max:255',
        ]);

        $userKanban = UserKanban::create($validated);
        $userKanban->load(['user', 'kanban']);

        return response()->json($userKanban, 201);
    }

    /**
     * Display the specified user kanban card.
     */
    public function show($id)
    {
        $userKanban = UserKanban::with(['user', 'kanban'])->findOrFail($id);

        return response()->json($userKanban);
    }

    /**
     * Update the specified user kanban card.
     */
    public function update(Request $request, $id)
    {
        $userKanban = UserKanban::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'kanban_id' => 'sometimes|required|exists:kanbans,id',
            'description' => 'nullable|string',
            'date' => 'sometimes|nullable|date',
            'hour' => 'nullable|date_format:H:i',
            'status' => 'nullable|string|max:255',
        ]);

        $userKanban->update($validated);
        $userKanban->load(['user', 'kanban']);

        return response()->json($userKanban);
    }

    /**
     * Remove the specified user kanban card.
     */
    public function destroy($id)
    {
        $userKanban = UserKanban::findOrFail($id);
        $userKanban->delete();

        return response()->json(['message' => 'User kanban card deleted successfully'], 200);
    }

    /**
     * Move a card to another kanban column.
     */
    public function move(Request $request, $id)
    {
        $userKanban = UserKanban::findOrFail($id);

        $validated = $request->validate([
            'kanban_id' => 'required|exists:kanbans,id',
        ]);

        $userKanban->update(['kanban_id' => $validated['kanban_id']]);
        $userKanban->load(['user', 'kanban']);

        return response()->json($userKanban);
    }

    /**
     * Get all cards for a specific user.
     */
    public function getByUser($userId)
    {
        $userKanbans = UserKanban::with(['kanban'])
            ->where('user_id', $userId)
            ->orderBy('date')
            ->get();

        return response()->json($userKanbans);
    }
}
