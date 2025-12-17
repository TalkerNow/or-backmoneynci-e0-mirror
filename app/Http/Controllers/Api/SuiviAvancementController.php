<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuiviAvancement;
use Illuminate\Http\Request;

class SuiviAvancementController extends Controller
{
    /**
     * 0) Récupérer tous les suivis + les documents associés
     * (documents.id lié à suivi_avancement.facture_id)
     */
    public function getAllWithDocuments()
    {
        $suivis = SuiviAvancement::query()
            ->leftJoin('documents', 'suivi_avancement.facture_id', '=', 'documents.id')
            ->select(
                // Champs SuiviAvancement
                'suivi_avancement.id as suivi_id',
                'suivi_avancement.client_id',
                'suivi_avancement.facture_id',
                'suivi_avancement.step1_completed_at',
                'suivi_avancement.step2_completed_at',
                'suivi_avancement.step3_completed_at',
                'suivi_avancement.step4_completed_at',
                'suivi_avancement.step5_completed_at',
                'suivi_avancement.step6_completed_at',
                'suivi_avancement.step7_completed_at',

                // Champs Documents
                'documents.id as document_id',
                'documents.link_to_documents',
                'documents.type',
                'documents.document_state',
                'documents.comment',
                'documents.payment_method',
                'documents.advanced_payment',
                'documents.pre_payment',
                'documents.end_payment',
                'documents.status_payment',
                'documents.subscribe_services',
                'documents.values',
                'documents.user_id',
                'documents.parent_id',
                'documents.deposit_date',
                'documents.sold_date',
                'documents.creator_id',
                'documents.sold_dates',
                'documents.acompte_dates',
                'documents.unipro'
            )
            ->get();

        return response()->json($suivis);
    }

    /**
     * 1) Créer un avancement pour un client + une facture
     * Body attendu:
     * {
     *   "client_id": 123,
     *   "facture_id": 456
     * }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'  => ['required', 'integer'], // tu peux ajouter exists:clients,id
            'facture_id' => ['required', 'integer'], // idem exists:factures,id
        ]);

        // On évite les doublons client + facture
        $suivi = SuiviAvancement::firstOrCreate([
            'client_id'  => $data['client_id'],
            'facture_id' => $data['facture_id'],
        ]);

        return response()->json($suivi, 201);
    }

    public function getByClient(int $clientId)
    {
        // Tous les suivis (toutes les factures) pour ce client
        $suivis = SuiviAvancement::where('client_id', $clientId)->get();

        return response()->json($suivis);
    }

    public function getByClientAndFacture(int $clientId, int $factureId)
    {
        $suivi = SuiviAvancement::where('client_id', $clientId)
            ->where('facture_id', $factureId)
            ->first();

        if (!$suivi) {
            return response()->json([
                'message' => 'Aucun suivi trouvé pour ce client et cette facture.',
            ], 404);
        }

        return response()->json($suivi);
    }

    /**
     * 2) Ajouter une date d'avancement pour une step précise
     * Body attendu:
     * {
     *   "date": "2025-11-18 14:30:00"
     * }
     * -> échoue si la step a déjà une date
     */
    public function addStepDate(Request $request, int $id, int $step)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $column = $this->getStepColumn($step);

        $suivi = SuiviAvancement::findOrFail($id);

        if (!is_null($suivi->$column)) {
            return response()->json([
                'message' => "La step {$step} a déjà une date, utilise l'endpoint de modification."
            ], 422);
        }

        $suivi->$column = $validated['date'];
        $suivi->save();

        return response()->json($suivi);
    }

    /**
     * 3) Modifier la date d'une step
     * Body attendu:
     * {
     *   "date": "2025-11-20 09:00:00"
     * }
     * -> échoue si aucune date n'était définie
     */
    public function updateStepDate(Request $request, int $id, int $step)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $column = $this->getStepColumn($step);

        $suivi = SuiviAvancement::findOrFail($id);

        if (is_null($suivi->$column)) {
            return response()->json([
                'message' => "Aucune date n'est encore définie pour la step {$step}, utilise d'abord l'ajout."
            ], 422);
        }

        $suivi->$column = $validated['date'];
        $suivi->save();

        return response()->json($suivi);
    }

    /**
     * 4) Supprimer la date d'une step (la remettre à null)
     */
    public function deleteStepDate(int $id, int $step)
    {
        $column = $this->getStepColumn($step);

        $suivi = SuiviAvancement::findOrFail($id);

        $suivi->$column = null;
        $suivi->save();

        return response()->json([
            'message' => "Date de la step {$step} supprimée.",
            'suivi'   => $suivi,
        ]);
    }

    /**
     * 5) Supprimer un suivi complet
     */
    public function destroy($id)
    {
        $suivi = SuiviAvancement::findOrFail($id);
        $suivi->delete();

        return response()->json([
            'message' => 'Suivi supprimé avec succès.'
        ]);
    }

    /**
     * Petit helper privé pour sécuriser l'accès aux colonnes stepX_completed_at
     */
    private function getStepColumn(int $step): string
    {
        if ($step < 1 || $step > 7) {
            abort(400, 'Step invalide (doit être entre 1 et 7)');
        }

        return "step{$step}_completed_at";
    }
}
