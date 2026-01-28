<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kanban;
use Illuminate\Http\Request;

class KanbanController extends Controller
{
    /**
     * Display a listing of kanbans.
     */
    public function index()
    {
        $kanbans = Kanban::with('userKanbans.user')
            ->orderBy('order')
            ->get();

        return response()->json($kanbans);
    }

    /**
     * Store a newly created kanban.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'order' => 'nullable|integer',
        ]);

        // Si l'ordre n'est pas spécifié, mettre à la fin
        if (!isset($validated['order'])) {
            $validated['order'] = Kanban::max('order') + 1;
        }

        $kanban = Kanban::create($validated);

        return response()->json($kanban, 201);
    }

    /**
     * Display the specified kanban.
     */
    public function show($id)
    {
        $kanban = Kanban::with('userKanbans.user')->findOrFail($id);

        return response()->json($kanban);
    }

    /**
     * Update the specified kanban.
     */
    public function update(Request $request, $id)
    {
        $kanban = Kanban::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'order' => 'sometimes|required|integer',
        ]);

        $kanban->update($validated);

        return response()->json($kanban);
    }

    /**
     * Remove the specified kanban.
     */
    public function destroy($id)
    {
        $kanban = Kanban::findOrFail($id);
        $kanban->delete();

        return response()->json(['message' => 'Kanban deleted successfully'], 200);
    }

    /**
     * Reorder kanbans.
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'kanbans' => 'required|array',
            'kanbans.*.id' => 'required|exists:kanbans,id',
            'kanbans.*.order' => 'required|integer',
        ]);

        foreach ($validated['kanbans'] as $kanbanData) {
            Kanban::where('id', $kanbanData['id'])->update(['order' => $kanbanData['order']]);
        }

        return response()->json(['message' => 'Kanbans reordered successfully']);
    }
}
