<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DepartureRule;
use App\Models\DepartureRulesHistory;
use App\Services\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DepartureRulesController extends Controller
{
    /** Bornes de plausibilité — alignées sur la validation du PUT (update). */
    private const AGE_MIN_MONTHS = 720; // 60 ans
    private const AGE_MAX_MONTHS = 816; // 68 ans
    private const TRIM_MIN = 150;
    private const TRIM_MAX = 180;

    private function requireAuth(): ?JsonResponse
    {
        if (! auth('api')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return null;
    }

    private function requireAdmin(): ?JsonResponse
    {
        if ($err = $this->requireAuth()) {
            return $err;
        }
        $auth = auth('api')->user();
        if (strtolower($auth->role ?? '') !== 'admin') {
            return response()->json(['error' => 'Forbidden — accès réservé admin'], 403);
        }
        return null;
    }

    public function index(): JsonResponse
    {
        if ($err = $this->requireAuth()) {
            return $err;
        }
        $rules = DepartureRule::ordered()->get()->map->toApiArray();
        return response()->json($rules);
    }

    public function update(Request $request): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $data = $request->validate([
            'rules'              => 'required|array|size:21',
            'rules.*.id'         => 'required|integer|exists:departure_rules,id',
            'rules.*.age_months' => 'required|integer|between:720,816',
            'rules.*.trim'       => 'required|integer|between:150,180',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['rules'] as $row) {
                DepartureRule::where('id', $row['id'])->update([
                    'age_months' => $row['age_months'],
                    'trim'       => $row['trim'],
                    'updated_by' => auth('api')->id(),
                ]);
            }

            DepartureRulesHistory::create([
                'rules_json' => DepartureRule::ordered()->get()->toArray(),
                'saved_by'   => auth('api')->id(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function history(): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $history = DepartureRulesHistory::with('savedBy')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($h) => [
                'id'         => $h->id,
                'saved_by'   => $h->savedBy?->name,
                'created_at' => $h->created_at->toIso8601String(),
                'preview'    => collect($h->rules_json)->take(3)->map(fn ($r) => [
                    'trim'       => $r['trim'],
                    'age_months' => $r['age_months'],
                ]),
            ]);

        return response()->json($history);
    }

    public function restore(int $historyId): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $snapshot = DepartureRulesHistory::findOrFail($historyId);

        DB::transaction(function () use ($snapshot) {
            foreach ($snapshot->rules_json as $row) {
                DepartureRule::where('id', $row['id'])->update([
                    'age_months' => $row['age_months'],
                    'trim'       => $row['trim'],
                    'updated_by' => auth('api')->id(),
                ]);
            }

            DepartureRulesHistory::create([
                'rules_json' => $snapshot->rules_json,
                'saved_by'   => auth('api')->id(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Extraction assistée par IA d'un barème depuis une circulaire CNAV (PDF).
     *
     * Le PDF est envoyé à Gemini avec la liste des générations couvertes par la
     * table `departure_rules`. Gemini renvoie, par génération, l'âge légal (en
     * mois) et la durée d'assurance (trimestres) lus UNIQUEMENT dans les tableaux
     * métropole / régime général (SPM et Mayotte explicitement exclus).
     *
     * IMPORTANT : cette méthode ne PERSISTE rien. Elle retourne une *proposition*
     * (diff valeur actuelle → valeur extraite) que l'admin valide visuellement
     * avant d'appliquer via le PUT existant. C'est le garde-fou « validation
     * humaine » exigé par la fiabilité 100 % des calculs retraite.
     */
    public function importPdf(Request $request, GeminiClient $gemini): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480', // 20 Mo
        ]);

        $rows = DepartureRule::ordered()->get();
        if ($rows->isEmpty()) {
            return response()->json(['error' => 'Aucune ligne de barème à mettre à jour.'], 422);
        }

        $pdfBase64 = base64_encode(file_get_contents($request->file('pdf')->getRealPath()));

        try {
            // L'extraction d'une circulaire multi-pages dépasse les 120 s du chat
            // (observé ~160 s) : timeout dédié large pour éviter les faux échecs.
            $raw = $gemini->generateFromPdf(
                $this->extractionSystemPrompt(),
                $this->extractionUserMessage($rows),
                $pdfBase64,
                $this->extractionSchema(),
                300
            );
        } catch (Throwable $e) {
            Log::error('Barème import PDF — échec Gemini', ['msg' => $e->getMessage()]);
            return response()->json([
                'error' => "Échec de l'extraction IA : " . $e->getMessage(),
            ], 502);
        }

        $extracted = json_decode($raw, true);
        if (! is_array($extracted)) {
            Log::warning('Barème import PDF — JSON Gemini invalide', ['raw' => $raw]);
            return response()->json([
                'error' => "Réponse IA illisible — impossible d'extraire le barème.",
            ], 422);
        }

        // Indexe par sort_order pour le mapping ligne à ligne.
        $bySortOrder = [];
        foreach ($extracted as $item) {
            if (isset($item['sort_order'])) {
                $bySortOrder[(int) $item['sort_order']] = $item;
            }
        }

        $proposed     = [];
        $warnings     = [];
        $changedCount = 0;

        foreach ($rows as $row) {
            $hit       = $bySortOrder[$row->sort_order] ?? null;
            $genLabel  = $this->generationLabel($rows, $row);
            $age       = $this->sanitizeAge($hit['age_months'] ?? null);
            $trim      = $this->sanitizeTrim($hit['trim'] ?? null);

            if ($age === null || $trim === null) {
                $warnings[] = $genLabel;
            }

            // Valeur retenue : extraite si plausible, sinon on conserve l'actuelle.
            $propAge  = $age  ?? $row->age_months;
            $propTrim = $trim ?? $row->trim;
            $changed  = ($propAge !== $row->age_months) || ($propTrim !== $row->trim);
            if ($changed) {
                $changedCount++;
            }

            $proposed[] = [
                'id'                 => $row->id,
                'key_max'            => $row->key_max,
                'generation'         => $genLabel,
                'current_age_months' => $row->age_months,
                'current_trim'       => $row->trim,
                'proposed_age_months' => $propAge,
                'proposed_trim'      => $propTrim,
                'found'              => $age !== null && $trim !== null,
                'changed'            => $changed,
            ];
        }

        return response()->json([
            'source'        => $request->file('pdf')->getClientOriginalName(),
            'changed_count' => $changedCount,
            'warnings'      => $warnings,
            'proposed'      => $proposed,
        ]);
    }

    /** Valide une valeur d'âge en mois extraite ; null si absente ou hors bornes. */
    private function sanitizeAge($value): ?int
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }
        $v = (int) $value;
        return ($v >= self::AGE_MIN_MONTHS && $v <= self::AGE_MAX_MONTHS) ? $v : null;
    }

    /** Valide un nombre de trimestres extrait ; null si absent ou hors bornes. */
    private function sanitizeTrim($value): ?int
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }
        $v = (int) $value;
        return ($v >= self::TRIM_MIN && $v <= self::TRIM_MAX) ? $v : null;
    }

    private function extractionSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant d'extraction réglementaire pour un cabinet de conseil en retraite.
On te fournit une circulaire officielle (CNAV) au format PDF, et une liste de générations
(plages d'années de naissance). Pour CHAQUE génération demandée, lis dans le PDF :
  - l'âge légal de départ à la retraite ;
  - la durée d'assurance requise pour le taux plein (en trimestres).

RÈGLES STRICTES — la fiabilité doit être de 100 % :
- N'utilise QUE les tableaux « métropole » / régime général (France métropolitaine et DROM
  relevant du régime général). IGNORE TOTALEMENT Saint-Pierre-et-Miquelon (SPM) et Mayotte :
  ces régimes ont un calendrier décalé et ne doivent JAMAIS être mélangés au barème métropole.
- Exprime l'âge légal en MOIS : 62 ans = 744, 64 ans = 768, 62 ans et 6 mois = 750.
- La durée d'assurance est un nombre entier de TRIMESTRES.
- Si une génération n'apparaît PAS explicitement dans le PDF, renvoie null pour age_months
  ET pour trim. N'INVENTE jamais de valeur, n'interpole pas, ne déduis pas une tendance.
- Ne renvoie une valeur que si le PDF l'affirme noir sur blanc pour la génération demandée.
PROMPT;
    }

    /** Construit la consigne listant les générations à renseigner (sans valeurs actuelles, pour ne pas biaiser). */
    private function extractionUserMessage($rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = '- sort_order ' . $row->sort_order . ' : ' . $this->generationLabel($rows, $row);
        }

        return "Pour chaque génération ci-dessous (identifiée par son sort_order), renvoie "
            . "age_months (âge légal en mois) et trim (trimestres requis) lus dans le PDF, "
            . "métropole / régime général UNIQUEMENT :\n\n"
            . implode("\n", $lines);
    }

    /** Schéma JSON forcé en sortie de Gemini (un objet par génération). */
    private function extractionSchema(): array
    {
        return [
            'type'  => 'ARRAY',
            'items' => [
                'type'       => 'OBJECT',
                'properties' => [
                    'sort_order' => ['type' => 'INTEGER'],
                    'age_months' => ['type' => 'INTEGER', 'nullable' => true],
                    'trim'       => ['type' => 'INTEGER', 'nullable' => true],
                ],
                'required' => ['sort_order'],
            ],
        ];
    }

    /**
     * Décrit la plage de générations couverte par une ligne, à partir des bornes
     * `key_max` (format AAAAMM) : la ligne couvre les naissances postérieures au
     * key_max de la ligne précédente, jusqu'à son propre key_max inclus.
     */
    private function generationLabel($rows, DepartureRule $row): string
    {
        $idx  = $rows->search(fn ($r) => $r->id === $row->id);
        $prev = $idx > 0 ? $rows[$idx - 1] : null;

        $upper = $row->key_max;             // borne haute (null = pas de borne haute)
        $lower = $prev?->key_max;           // borne basse exclusive (clé de la ligne précédente)

        if ($lower === null && $upper !== null) {
            return 'assurés nés jusqu\'en ' . $this->ymLabel($upper) . ' inclus';
        }
        if ($upper === null && $lower !== null) {
            return 'assurés nés à partir de ' . $this->ymLabel($this->nextMonth($lower));
        }
        if ($upper === null && $lower === null) {
            return 'toutes générations';
        }

        return 'assurés nés de ' . $this->ymLabel($this->nextMonth($lower))
            . ' à ' . $this->ymLabel($upper);
    }

    /** AAAAMM -> "mois AAAA" en français. */
    private function ymLabel(int $ym): string
    {
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                   'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $year  = intdiv($ym, 100);
        $month = $ym % 100;
        return ($months[$month - 1] ?? '?') . ' ' . $year;
    }

    /** Mois suivant un AAAAMM (passage d'année géré). */
    private function nextMonth(int $ym): int
    {
        $year  = intdiv($ym, 100);
        $month = $ym % 100;
        return $month >= 12 ? ($year + 1) * 100 + 1 : $year * 100 + ($month + 1);
    }
}
