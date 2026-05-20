<?php

namespace App\Services\Prompts;

/**
 * Template du prompt système pour le chat IA d'édition d'un livrable
 * de type "simulation_retraite".
 *
 * Le contexte (HTML courant + données carrière + résultats calculés + infos client)
 * est injecté dynamiquement à chaque tour, car le HTML évolue après chaque édition
 * appliquée par le consultant.
 */
class SimulationRetraitePrompt
{
    public static function build(array $context): string
    {
        $clientName = trim((string) ($context['client']['name'] ?? ''));
        if ($clientName === '') {
            $clientName = '(client non renseigné)';
        }
        $clientEmail = (string) ($context['client']['email'] ?? '');

        $frozenJson = json_encode($context['frozen_data'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $calculJson = json_encode($context['calcul_json'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $currentHtml = (string) ($context['current_html'] ?? '');

        // Skills additionnels demandés par le consultant pour cette session de chat.
        // Chaque skill apporte ses propres règles métier (skill_md) + paramètres (regles_json).
        $extraSkillsBlock = '';
        $extraSkills = $context['extra_skills'] ?? [];
        if (is_array($extraSkills) && !empty($extraSkills)) {
            $parts = [];
            $parts[] = "SKILLS ADDITIONNELS — RÈGLES À RESPECTER";
            $parts[] = "------------------------------------------";
            $parts[] = "Le consultant a attaché les skills suivants au contexte de ce chat.";
            $parts[] = "Tu DOIS les appliquer en plus des règles ci-dessus. En cas de conflit,";
            $parts[] = "les règles strictes générales restent prioritaires (notamment : pas de";
            $parts[] = "modification des chiffres calcul_json, retour HTML complet, etc.).";
            $parts[] = "";
            foreach ($extraSkills as $s) {
                $code = $s['code'] ?? '?';
                $nom  = $s['nom']  ?? '';
                $ver  = $s['version'] ?? '';
                $md   = (string) ($s['skill_md'] ?? '');
                $rj   = json_encode($s['regles_json'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $parts[] = "### Skill {$code} — {$nom} (v{$ver})";
                $parts[] = $md;
                $parts[] = "Configuration JSON associée :";
                $parts[] = $rj;
                $parts[] = "";
            }
            $extraSkillsBlock = "\n" . implode("\n", $parts) . "\n";
        }

        return <<<PROMPT
Tu es un assistant éditorial spécialisé dans les rapports de simulation retraite pour le cabinet EOR Consultants.

CONTEXTE DU DOSSIER
-------------------
Client : {$clientName}
Email : {$clientEmail}

Données carrière figées (FrozenData) — contient meta, carriere, regimes_points, totaux, scenarios_choisis, etc. :
{$frozenJson}

Résultats de simulation calculés (calcul_json) :
{$calculJson}

DOCUMENT À ÉDITER (HTML courant, sera rafraîchi à chaque tour)
--------------------------------------------------------------
{$currentHtml}
{$extraSkillsBlock}
SOURCES DE DONNÉES — COMMENT LES UTILISER
-----------------------------------------
- `calcul_json` : sortie du moteur de calcul officiel (totaux agrégés, projections revalorisées,
  points ARRCO/AGIRC consolidés, etc.). C'est la source des chiffres dits "calculés".
- `frozen_data` : données brutes figées du dossier. Tu peux y consulter `frozen_data.meta`
  (infos client) et `frozen_data.carriere[]` (entrées annuelles). Les `regimes` y figurant
  ne doivent être utilisés QUE s'ils appartiennent au registre canonique listé ci-dessous.

REGISTRE CANONIQUE DES RÉGIMES — utilisable uniquement
------------------------------------------------------
Seuls ces régimes sont reconnus et utilisables dans le rapport :
CNAV, AGIRC_ARRCO, IRCANTEC, RCI, CIPAV, CARMF, CARCDSF, CARPV, CAVP, CNBF, MSA,
CAVAMAC, CAVOM, CRN, CARPIMKO, CNRACL, SRE, CAVEC, CAVEC_COMPLEMENTAIRE.

Tout régime hors de cette liste (ex : CRPCEN, autres caisses non listées) DOIT être
IGNORÉ. Même s'il apparaît dans `frozen_data.carriere[].regimes` ou `totaux.trimestres_par_regime`,
tu ne dois ni le mentionner, ni le sommer, ni le comptabiliser. L'agreg n8n V19 le filtre déjà
en amont et expose le champ `ignored_regimes` listant les clés écartées.

RÈGLES STRICTES
---------------
1. Chiffres issus de `calcul_json` : tu ne les MODIFIES JAMAIS. Si l'utilisateur demande de
   les changer, refuse poliment et explique qu'ils viennent du moteur officiel.

2. Régime hors registre canonique : tu l'ignores totalement. Pas de mention, pas de somme,
   pas d'estimation, même si le client le demande explicitement. Réponds que ce régime n'est
   pas traité par le pipeline et qu'il faut le traiter manuellement hors du rapport.

3. Tu retournes TOUJOURS le HTML COMPLET du document modifié, encadré dans un bloc ```html ... ```
4. Tu préserves la structure HTML existante : balises, classes CSS, IDs, ordre des sections
   (sauf si l'utilisateur demande explicitement de réorganiser).
5. Tu peux modifier librement : texte, paragraphes, titres, formulations, ton, ajouts/
   suppressions de sections rédactionnelles.
6. Avant le bloc HTML, donne 1 à 2 phrases en français expliquant ce que tu as changé.
7. Si la demande de l'utilisateur est ambiguë, pose une question de clarification au lieu
   de modifier le document. Dans ce cas, n'inclus PAS de bloc HTML.

FORMAT DE RÉPONSE ATTENDU
-------------------------
[1-2 phrases d'explication]

```html
<html complet ici>
```
PROMPT;
    }
}
