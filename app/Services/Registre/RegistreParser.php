<?php

namespace App\Services\Registre;

/**
 * Parser PUR du registre d'erreurs de cohérence (markdown).
 *
 * Source de vérité = ligne prompts.REGISTRE_ERREURS_COHERENCE (prompt_text).
 * Aucune dépendance Laravel/DB ici — uniquement du parsing de chaîne, pour
 * être réutilisable côté AdminEngineChatController (UI registre) ET côté
 * RegistreRules (enforcement). Évite la duplication de regex.
 */
class RegistreParser
{
    /**
     * Parse le document complet → stats + règles actives/archivées + template.
     */
    public function parse(string $md): array
    {
        $stats = [
            'total_errors'         => null,
            'active_rules_count'   => null,
            'archived_rules_count' => null,
            'last_updated'         => null,
        ];

        if (preg_match('/\*\*Total erreurs capturées\*\*\s*:\s*(\d+)/u', $md, $m)) $stats['total_errors'] = (int) $m[1];
        if (preg_match('/\*\*Règles actives\*\*\s*:\s*(\d+)/u', $md, $m))           $stats['active_rules_count'] = (int) $m[1];
        if (preg_match('/\*\*Règles archivées\*\*\s*:\s*(\d+)/u', $md, $m))         $stats['archived_rules_count'] = (int) $m[1];
        if (preg_match('/\*\*Dernière mise à jour\*\*\s*:\s*([\d\/]+)/u', $md, $m)) $stats['last_updated'] = $m[1];

        $version     = null;
        $lastUpdated = null;
        if (preg_match('/\*\*Version\*\*\s*:\s*([\d.]+)/u', $md, $m))               $version = $m[1];
        if (preg_match('/\*\*Dernière mise à jour\*\*\s*:\s*([\d\/]+)/u', $md, $m)) $lastUpdated = $m[1];

        $template = '';
        if (preg_match('/## 📋 TEMPLATE[\s\S]*?```markdown([\s\S]*?)```/u', $md, $m)) {
            $template = trim($m[1]);
        }

        $allRules      = $this->parseAll($md);
        $activeRules   = array_values(array_filter($allRules, fn($r) => str_contains($r['statut'] ?? '', '✅')));
        $archivedRules = array_values(array_filter($allRules, fn($r) => str_contains($r['statut'] ?? '', '❌')));

        $stats['active_rules_count']   = count($activeRules);
        $stats['archived_rules_count'] = count($archivedRules);

        return [
            'stats'          => $stats,
            'active_rules'   => $activeRules,
            'archived_rules' => $archivedRules,
            'template'       => $template,
            'version'        => $version,
            'last_updated'   => $lastUpdated,
        ];
    }

    /**
     * Parse toutes les règles RXXX du document en une liste plate.
     *
     * @return array<int,array<string,?string>> chaque règle : code, title, et les
     *         champs date_ajout, cas_origine, prompt_concerne, consultant,
     *         erreur_detectee, condition_python, message_erreur, niveau, statut, impact
     */
    public function parseAll(string $md): array
    {
        $fields = [
            'date_ajout'       => "Date d'ajout",
            'cas_origine'      => 'Cas origine',
            'prompt_concerne'  => 'Prompt concerné',
            'consultant'       => 'Consultant',
            'erreur_detectee'  => 'Erreur détectée',
            'condition_python' => 'Condition Python',
            'message_erreur'   => "Message d'erreur",
            'niveau'           => 'Niveau',
            'statut'           => 'Statut',
            'impact'           => 'Impact',
        ];

        $parts = preg_split('/(?=### R\d+\s*\|)/u', $md);
        $rules = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if (!preg_match('/^### (R\d{3,})\s*\|\s*(.+)/u', $part, $header)) continue;

            $rule = ['code' => trim($header[1]), 'title' => trim($header[2])];

            foreach ($fields as $key => $label) {
                if (preg_match('/\*\*' . preg_quote($label, '/') . '\*\*\s*:\s*`?([^`\n]+)`?/u', $part, $fm)) {
                    $rule[$key] = trim($fm[1]);
                } else {
                    $rule[$key] = null;
                }
            }

            if (preg_match('/\*\*Impact\*\*\s*:\s*(.+)/u', $part, $fm)) {
                $rule['impact'] = trim($fm[1]);
            }

            $rules[] = $rule;
        }

        return $rules;
    }
}
