<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimulatorDifficultyResult;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SimulatorDifficultyResultController extends Controller
{
    public function index(Request $request)
    {
        $q = SimulatorDifficultyResult::query()->orderByDesc('id');

        // petits filtres pratiques
        if ($request->filled('email')) {
            $q->where('email', $request->string('email'));
        }
        if ($request->filled('external_contact_id')) {
            $q->where('external_contact_id', $request->integer('external_contact_id'));
        }

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }

    public function show(SimulatorDifficultyResult $simulatorDifficultyResult)
    {
        return response()->json($simulatorDifficultyResult);
    }

    public function store(Request $request)
    {
        // accepte:
        //  - { ... } (flat)
        //  - { "contact": { ... } }
        //  - { "contacts": [ { ... } ] }
        $contact = $this->extractContactPayload($request);

        // validation minimale (tu peux durcir si tu veux)
        $request->validate([
            'email' => ['nullable','email'],
            'contact.email' => ['nullable','email'],
            'contacts.0.email' => ['nullable','email'],
        ]);

        $data = $this->mapPayloadToDb($contact);

        if (empty($data['email'])) {
            return response()->json([
                'message' => 'email is required (either at root, contact.email, or contacts[0].email)',
            ], 422);
        }

        $row = SimulatorDifficultyResult::create($data);

        return response()->json($row, 201);
    }

    public function update(Request $request, SimulatorDifficultyResult $simulatorDifficultyResult)
    {
        $contact = $this->extractContactPayload($request);

        $request->validate([
            'email' => ['nullable','email'],
            'contact.email' => ['nullable','email'],
            'contacts.0.email' => ['nullable','email'],
        ]);

        $data = $this->mapPayloadToDb($contact);

        // en update, on autorise partiel : on ne remplace pas par null si absent
        $data = array_filter($data, fn($v) => $v !== null);

        $simulatorDifficultyResult->fill($data)->save();

        return response()->json($simulatorDifficultyResult);
    }

    public function destroy(SimulatorDifficultyResult $simulatorDifficultyResult)
    {
        $simulatorDifficultyResult->delete();
        return response()->json(null, 204);
    }

    // -------------------------
    // Helpers
    // -------------------------

    private function extractContactPayload(Request $request): array
    {
        if (is_array($request->input('contact'))) {
            return $request->input('contact');
        }

        $contacts0 = $request->input('contacts.0');
        if (is_array($contacts0)) {
            return $contacts0;
        }

        return $request->all(); // payload flat
    }

    private function mapPayloadToDb(array $contact): array
    {
        $attr = is_array($contact['attributes'] ?? null) ? $contact['attributes'] : [];

        $data = [
            // contact meta
            'external_contact_id' => $contact['id'] ?? null,
            'email' => $contact['email'] ?? null,
            'email_blacklisted' => $this->toBool($contact['emailBlacklisted'] ?? null) ?? false,
            'sms_blacklisted' => $this->toBool($contact['smsBlacklisted'] ?? null) ?? false,
            'external_created_at' => $contact['createdAt'] ?? null,
            'external_modified_at' => $contact['modifiedAt'] ?? null,
            'list_ids' => $contact['listIds'] ?? null,
            'list_unsubscribed' => $contact['listUnsubscribed'] ?? null,

            // attributs “profil”
            'nom' => $attr['NOM'] ?? null,
            'prenom' => $attr['PRENOM'] ?? null,
            'civilite' => $attr['CIVILITE'] ?? null,
            'statut' => $attr['STATUT'] ?? null,
            'date_naissance' => $attr['DATE_NAISSANCE'] ?? null,
            'code_postal' => isset($attr['CODE_POSTAL']) ? (string) $attr['CODE_POSTAL'] : null,
            'nbr_enfants' => isset($attr['NBR_ENFANTS']) ? (int) $attr['NBR_ENFANTS'] : null,
            'score' => isset($attr['SCORE']) ? (int) $attr['SCORE'] : null,

            // simulateur difficulté
            'q1' => $attr['SIMULATEUR_DIFFICULTE_Q1'] ?? null,
            'q2' => $attr['SIMULATEUR_DIFFICULTE_Q2'] ?? null,
            'q3' => $attr['SIMULATEUR_DIFFICULTE_Q3'] ?? null,
            'q4' => $attr['SIMULATEUR_DIFFICULTE_Q4'] ?? null,
            'q5' => $attr['SIMULATEUR_DIFFICULTE_Q5'] ?? null,
            'q6' => $attr['SIMULATEUR_DIFFICULTE_Q6'] ?? null,
            'q7' => $attr['SIMULATEUR_DIFFICULTE_Q7'] ?? null,
            'q8' => $attr['SIMULATEUR_DIFFICULTE_Q8'] ?? null,
            'q9' => $attr['SIMULATEUR_DIFFICULTE_Q9'] ?? null,
            'q10' => $attr['SIMULATEUR_DIFFICULTE_Q10'] ?? null,

            'newsletter' => $this->toBool($attr['SIMULATEUR_DIFFICULTE_NEWSLETTER'] ?? null) ?? false,
            'automation_recap_retraite' => $this->toBool($attr['AUTOMATION_RECAP_RETRAITE'] ?? null) ?? false,
            'date_depart' => $attr['SIMULATEUR_DIFFICULTE_DATE_DEPART'] ?? null,

            // backup payload
            'raw_payload' => $contact,
        ];

        // Support payload "flat" (si tu envoies directement q1, etc.)
        $flatOverrides = [
            'external_contact_id','email','nom','prenom','civilite','statut',
            'date_naissance','code_postal','nbr_enfants','score',
            'q1','q2','q3','q4','q5','q6','q7','q8','q9','q10',
            'newsletter','automation_recap_retraite','date_depart',
            'email_blacklisted','sms_blacklisted','list_ids','list_unsubscribed',
            'external_created_at','external_modified_at',
        ];

        foreach ($flatOverrides as $k) {
            if (array_key_exists($k, $contact)) {
                $data[$k] = $contact[$k];
            }
        }

        // normalise bool si flat
        foreach (['newsletter','automation_recap_retraite','email_blacklisted','sms_blacklisted'] as $b) {
            if (array_key_exists($b, $data)) {
                $data[$b] = $this->toBool($data[$b]) ?? $data[$b];
            }
        }

        return $data;
    }

    private function toBool(mixed $v): ?bool
    {
        if ($v === null) return null;
        if (is_bool($v)) return $v;

        if (is_int($v)) return $v === 1;

        if (is_string($v)) {
            $s = strtolower(trim($v));
            return match ($s) {
                '1','true','yes','oui','y' => true,
                '0','false','no','non','n' => false,
                default => null,
            };
        }

        return null;
    }
}
