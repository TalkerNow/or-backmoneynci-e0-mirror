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
            'title' => 'required|string|max:255|unique:kanbans,title',
            'order' => 'nullable|integer|min:0',
        ], [
            'title.required' => 'Le titre est obligatoire',
            'title.unique' => 'Un kanban avec ce titre existe déjà',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères',
            'order.min' => 'L\'ordre doit être un nombre positif',
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
        $kanban = Kanban::with('userKanbans.user')->find($id);
        
        if (!$kanban) {
            return response()->json([
                'message' => 'Kanban introuvable',
                'error' => 'kanban_not_found'
            ], 404);
        }

        return response()->json($kanban);
    }

    /**
     * Update the specified kanban.
     */
    public function update(Request $request, $id)
    {
        $kanban = Kanban::find($id);
        
        if (!$kanban) {
            return response()->json([
                'message' => 'Kanban introuvable',
                'error' => 'kanban_not_found'
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255|unique:kanbans,title,' . $id,
            'order' => 'sometimes|required|integer|min:0',
        ], [
            'title.required' => 'Le titre est obligatoire',
            'title.unique' => 'Un autre kanban avec ce titre existe déjà',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères',
            'order.required' => 'L\'ordre est obligatoire',
            'order.min' => 'L\'ordre doit être un nombre positif',
        ]);

        $kanban->update($validated);
        $kanban->refresh();

        return response()->json($kanban);
    }

    /**
     * Remove the specified kanban.
     */
    public function destroy($id)
    {
        $kanban = Kanban::find($id);
        
        if (!$kanban) {
            return response()->json([
                'message' => 'Kanban introuvable',
                'error' => 'kanban_not_found'
            ], 404);
        }
        
        // Vérifier si le kanban contient des cartes
        if ($kanban->userKanbans()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer ce kanban car il contient des rendez-vous',
                'error' => 'kanban_not_empty'
            ], 422);
        }
        
        $kanban->delete();

        return response()->json(['message' => 'Kanban supprimé avec succès'], 200);
    }

    /**
     * Reorder kanbans.
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'kanbans' => 'required|array|min:1',
            'kanbans.*.id' => 'required|exists:kanbans,id',
            'kanbans.*.order' => 'required|integer|min:0',
        ], [
            'kanbans.required' => 'La liste des kanbans est obligatoire',
            'kanbans.min' => 'Au moins un kanban doit être fourni',
            'kanbans.*.id.required' => 'L\'ID du kanban est obligatoire',
            'kanbans.*.id.exists' => 'Un des kanbans n\'existe pas',
            'kanbans.*.order.required' => 'L\'ordre est obligatoire pour chaque kanban',
            'kanbans.*.order.min' => 'L\'ordre doit être un nombre positif',
        ]);

        foreach ($validated['kanbans'] as $kanbanData) {
            Kanban::where('id', $kanbanData['id'])->update(['order' => $kanbanData['order']]);
        }

        return response()->json(['message' => 'Kanbans réorganisés avec succès']);
    }
}
