<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\Prompt;
use App\Models\PromptHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromptsController extends Controller
{
    /**
     * Get all prompts
     * Permet de filtrer par type si nécessaire
     */
    public function index(Request $request)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $query = Prompt::with('creator');

        // Filtrer par type si spécifié
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filtrer par créateur si spécifié (réservé aux admin/Consultant)
        if ($request->has('created_by')) {
            if ($auth->role === 'admin' || $auth->role === 'Consultant') {
                $query->where('created_by', $request->created_by);
            }
        }

        // Filtrer par créateur si l'utilisateur n'est pas admin
        if ($auth->role !== 'admin' && $auth->role !== 'Consultant') {
            $query->where('created_by', $auth->id);
        }

        $prompts = $query->orderBy('created_at', 'DESC')->get();

        return response()->json($prompts);
    }

    /**
     * Get a specific prompt with its history
     */
    public function show(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $query = Prompt::with(['creator', 'history.creator']);

        $prompt = $query->find($id);

        if (!$prompt) {
            return response()->json(['error' => 'Prompt not found'], 404);
        }

        // Vérifier les permissions
        if ($auth->role !== 'admin' && $auth->role !== 'Consultant' && $prompt->created_by !== $auth->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json($prompt);
    }

    /**
     * Create a new prompt
     */
    public function store(Request $request)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:general,email,rapport,analyse,autre,role',
            'prompt_text' => 'required|string',
        ], [
            'type.in' => 'Type invalide. Types autorisés: general, email, rapport, analyse, autre, role.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $prompt = Prompt::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'prompt_text' => $request->prompt_text,
            'created_by' => $auth->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prompt créé avec succès',
            'prompt' => $prompt->load('creator')
        ], 201);
    }

    /**
     * Update a prompt
     * L'historique est géré automatiquement par le modèle
     */
    public function update(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $prompt = Prompt::find($id);

        if (!$prompt) {
            return response()->json(['error' => 'Prompt not found'], 404);
        }

        // Vérifier les permissions
        if ($auth->role !== 'admin' && $auth->role !== 'Consultant' && $prompt->created_by !== $auth->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:general,email,rapport,analyse,autre,role',
            'prompt_text' => 'sometimes|required|string',
        ], [
            'type.in' => 'Type invalide. Types autorisés: general, email, rapport, analyse, autre, role.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $prompt->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Prompt mis à jour avec succès',
            'prompt' => $prompt->load(['creator', 'history.creator'])
        ]);
    }

    /**
     * Delete a prompt
     */
    public function destroy(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $prompt = Prompt::find($id);

        if (!$prompt) {
            return response()->json(['error' => 'Prompt not found'], 404);
        }

        // Seul l'admin, consultant ou le créateur peut supprimer
        if ($auth->role !== 'admin' && $auth->role !== 'Consultant' && $prompt->created_by !== $auth->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $prompt->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prompt supprimé avec succès'
        ]);
    }

    /**
     * Get history for a specific prompt
     */
    public function history(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $prompt = Prompt::find($id);

        if (!$prompt) {
            return response()->json(['error' => 'Prompt not found'], 404);
        }

        // Vérifier les permissions
        if ($auth->role !== 'admin' && $auth->role !== 'Consultant' && $prompt->created_by !== $auth->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $history = $prompt->history()->with('creator')->get();

        return response()->json($history);
    }

    /**
     * Restore a specific version from history
     */
    public function restore(Request $request, $id, $version)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        $prompt = Prompt::find($id);

        if (!$prompt) {
            return response()->json(['error' => 'Prompt not found'], 404);
        }

        // Vérifier les permissions
        if ($auth->role !== 'admin' && $auth->role !== 'Consultant' && $prompt->created_by !== $auth->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $historyEntry = PromptHistory::where('prompt_id', $id)
            ->where('version', $version)
            ->first();

        if (!$historyEntry) {
            return response()->json(['error' => 'Version not found'], 404);
        }

        // Restaurer le texte de cette version
        $prompt->update([
            'prompt_text' => $historyEntry->prompt_text
        ]);

        return response()->json([
            'success' => true,
            'message' => "Version $version restaurée avec succès",
            'prompt' => $prompt->load(['creator', 'history.creator'])
        ]);
    }
}
