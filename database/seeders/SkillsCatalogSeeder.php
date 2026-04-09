<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SkillsCatalog;

class SkillsCatalogSeeder extends Seeder
{
    /**
     * Chemin vers les fichiers source du client.
     * Relatif à la racine du projet Laravel (backmoneynci/).
     */
    private function clientFilesPath(): string
    {
        return env('SKILLS_PATH', base_path('../client_files/01_REGLEMENTATION/skills'));
    }

    public function run()
    {
        $this->command->info('Seeding skills_catalog from client files...');

        $skills = [
            // ===== SKILLS DE VALIDATION (priority 0) =====
            [
                'folder'         => 'skill_validation_autocontrole',
                'skill_id'       => 'SKILL_validation_autocontrole_v1',
                'nom'            => 'Autocontrôle Zéro Erreur',
                'code'           => 'AUTOCONTROLE',
                'version'        => '1.1',
                'type'           => 'skill_validation',
                'description'    => 'Validation Gate 1 (données obligatoires) + Gate 2 (cohérence). Architecture fail-safe : impossible d\'avancer sans validation complète.',
                'skill_md_file'  => 'SKILL_validation_autocontrole.md',
                'regles_file'    => 'validation_autocontrole_regles.json',
                'calcul_file'    => 'validation_autocontrole.py',
                'tags'           => ['validation', 'autocontrole', 'gate1', 'gate2', 'fail-safe'],
                'priority'       => 0,
            ],
            [
                'folder'         => 'skill_validation_continuite',
                'skill_id'       => 'SKILL_validation_continuite_v1',
                'nom'            => 'Validation Continuité de Carrière',
                'code'           => 'CONTINUITE',
                'version'        => '1.0',
                'type'           => 'skill_validation',
                'description'    => 'Vérifie la continuité chronologique de la carrière, détecte les trous et incohérences.',
                'skill_md_file'  => 'SKILL_validation_continuite.md',
                'regles_file'    => 'validation_continuite_regles.json',
                'calcul_file'    => 'validation_continuite_carriere.py',
                'tags'           => ['validation', 'continuite', 'carriere', 'trous'],
                'priority'       => 0,
            ],

            // ===== SKILL DE PRÉ-TRAITEMENT (priority 1) =====
            [
                'folder'         => 'skill_normalisation_question',
                'skill_id'       => 'SKILL_normalisation_question_v1',
                'nom'            => 'Normalisation de Question',
                'code'           => 'NORMALISATION',
                'version'        => '1.0',
                'type'           => 'skill_preprocessing',
                'description'    => 'Normalise les questions des experts-comptables et clients en requêtes structurées pour le moteur de calcul.',
                'skill_md_file'  => 'SKILL_normalisation_question.md',
                'regles_file'    => 'normalisation_regles.json',
                'calcul_file'    => null,
                'tags'           => ['normalisation', 'preprocessing', 'question'],
                'priority'       => 1,
            ],

            // ===== SKILL D'ANALYSE (priority 2) =====
            [
                'folder'         => 'skill_analyse_releve',
                'skill_id'       => 'SKILL_analyse_releve_v2',
                'nom'            => 'Analyse de Relevé de Carrière Retraite',
                'code'           => 'ANALYSE_RELEVE',
                'version'        => '2.0',
                'type'           => 'skill_analyse',
                'description'    => 'Analyse complète du relevé de carrière : détection anomalies, périodes manquantes, régimes concernés.',
                'skill_md_file'  => 'SKILL_analyse_releve.md',
                'regles_file'    => 'analyse_releve_regles.json',
                'calcul_file'    => 'calcul_analyse_carriere.py',
                'tags'           => ['analyse', 'releve', 'carriere', 'anomalies'],
                'priority'       => 2,
            ],

            // ===== SKILLS DE CALCUL RÉGIME (priority 3) =====
            [
                'folder'         => 'skill_cnav',
                'skill_id'       => 'SKILL_calcul_cnav_v1',
                'nom'            => 'Calcul Pension CNAV',
                'code'           => 'CNAV',
                'version'        => '1.0',
                'type'           => 'skill_calcul_regime',
                'description'    => 'Calcul pension BRUTE régime général (Sécurité Sociale). SAM, taux de liquidation, coefficient de proratisation. Article L.351-1 CSS.',
                'skill_md_file'  => 'SKILL_calcul_cnav.md',
                'regles_file'    => 'calcul_cnav_regles.json',
                'calcul_file'    => 'calcul_cnav.py',
                'tags'           => ['cnav', 'regime_base', 'pension', 'sam', 'taux_liquidation'],
                'priority'       => 3,
            ],
            [
                'folder'         => 'skill_complementaires',
                'skill_id'       => 'SKILL_complementaires_v1',
                'nom'            => 'Calcul des Pensions Complémentaires',
                'code'           => 'COMPLEMENTAIRES',
                'version'        => '1.0',
                'type'           => 'skill_calcul_regime',
                'description'    => 'Calcul pensions complémentaires AGIRC-ARRCO, IRCANTEC, RCI. Points, valeur de service, décote/surcote.',
                'skill_md_file'  => 'SKILL_complementaires.md',
                'regles_file'    => 'complementaires_regles.json',
                'calcul_file'    => 'calcul_complementaires.py',
                'tags'           => ['complementaires', 'agirc-arrco', 'ircantec', 'rci', 'points'],
                'priority'       => 3,
            ],

            // ===== SKILLS DISPOSITIFS SPÉCIAUX (priority 4) =====
            [
                'folder'         => 'skill_racl',
                'skill_id'       => 'SKILL_racl_v2',
                'nom'            => 'RACL - Retraite Anticipée Carrière Longue',
                'code'           => 'RACL',
                'version'        => '2.0',
                'type'           => 'skill_dispositif',
                'description'    => 'Éligibilité RACL : trimestres avant 16/18/20/21 ans, trimestres réputés cotisés, âge de départ anticipé. Circulaire CNAV 2023-14.',
                'skill_md_file'  => 'SKILL_racl.md',
                'regles_file'    => 'racl_regles.json',
                'calcul_file'    => 'calcul_racl.py',
                'tags'           => ['racl', 'carriere_longue', 'depart_anticipe'],
                'priority'       => 4,
            ],
            [
                'folder'         => 'skill_vplr',
                'skill_id'       => 'SKILL_vplr_v2',
                'nom'            => 'VPLR - Versement Pour La Retraite',
                'code'           => 'VPLR',
                'version'        => '2.0',
                'type'           => 'skill_dispositif',
                'description'    => 'Simulation rachat de trimestres : éligibilité, coût par trimestre (barème âge/option), impact sur pension. Circulaire CNAV 2025-01.',
                'skill_md_file'  => 'SKILL_vplr.md',
                'regles_file'    => 'vplr_regles.json',
                'calcul_file'    => 'vplr_calculs.py',
                'tags'           => ['vplr', 'rachat', 'trimestres', 'versement'],
                'priority'       => 4,
            ],
            [
                'folder'         => 'skill_retraite_progressive',
                'skill_id'       => 'SKILL_retraite_progressive_v1',
                'nom'            => 'Retraite Progressive',
                'code'           => 'RETRAITE_PROGRESSIVE',
                'version'        => '1.0',
                'type'           => 'skill_dispositif',
                'description'    => 'Éligibilité et simulation retraite progressive : temps partiel, fraction de pension, conditions d\'âge et durée. CSS L.351-15.',
                'skill_md_file'  => 'SKILL_retraite_progressive.md',
                'regles_file'    => 'retraite_progressive_regles.json',
                'calcul_file'    => 'calcul_retraite_progressive.py',
                'tags'           => ['retraite_progressive', 'temps_partiel', 'fraction_pension'],
                'priority'       => 4,
            ],
            [
                'folder'         => 'skill_cumul_emploi_retraite',
                'skill_id'       => 'SKILL_cumul_emploi_retraite_v1',
                'nom'            => 'Cumul Emploi Retraite',
                'code'           => 'CUMUL_EMPLOI_RETRAITE',
                'version'        => '1.0',
                'type'           => 'skill_dispositif',
                'description'    => 'Éligibilité cumul emploi-retraite intégral/plafonné, nouvelles cotisations créatrices de droits. Circulaire CNAV 2017-41.',
                'skill_md_file'  => 'SKILL_cumul_emploi_retraite.md',
                'regles_file'    => 'cumul_emploi_retraite_regles.json',
                'calcul_file'    => 'calcul_cumul_emploi_retraite.py',
                'tags'           => ['cumul_emploi_retraite', 'cer', 'reprise_activite'],
                'priority'       => 4,
            ],
            [
                'folder'         => 'skill_trimestres_etranger',
                'skill_id'       => 'SKILL_trimestres_etranger_v1',
                'nom'            => 'Trimestres Étranger',
                'code'           => 'TRIMESTRES_ETRANGER',
                'version'        => '1.0',
                'type'           => 'skill_dispositif',
                'description'    => 'Prise en compte des périodes étrangères : UE, conventions bilatérales, hors convention. Impact sur trimestres et taux.',
                'skill_md_file'  => 'SKILL_trimestres_etranger.md',
                'regles_file'    => 'trimestres_etranger_regles.json',
                'calcul_file'    => 'calcul_trimestres_etranger.py',
                'tags'           => ['trimestres_etranger', 'international', 'convention', 'ue'],
                'priority'       => 4,
            ],
            [
                'folder'         => 'skill_reversion',
                'skill_id'       => 'SKILL_reversion_v1',
                'nom'            => 'Pension de Réversion',
                'code'           => 'REVERSION',
                'version'        => '1.0',
                'type'           => 'skill_dispositif',
                'description'    => 'Éligibilité et calcul pension de réversion : conditions d\'âge, de ressources, taux de 54%. Articles L.353-1 à L.353-7 CSS.',
                'skill_md_file'  => 'SKILL_reversion.md',
                'regles_file'    => 'reversion_regles.json',
                'calcul_file'    => null,
                'tags'           => ['reversion', 'conjoint', 'deces'],
                'priority'       => 4,
            ],
        ];

        foreach ($skills as $config) {
            $this->seedOneSkill($config);
        }

        $this->command->info('Done! ' . count($skills) . ' skills seeded.');
    }

    private function seedOneSkill(array $config): void
    {
        $folderPath = $this->clientFilesPath() . '/' . $config['folder'];

        if (!is_dir($folderPath)) {
            $this->command->warn("  SKIP: Dossier introuvable — {$folderPath}");
            return;
        }

        $skillMdPath = $folderPath . '/' . $config['skill_md_file'];
        if (!file_exists($skillMdPath)) {
            $this->command->warn("  SKIP: {$config['skill_md_file']} introuvable dans {$config['folder']}");
            return;
        }
        $skillMd = file_get_contents($skillMdPath);

        $reglesPath = $folderPath . '/' . $config['regles_file'];
        if (!file_exists($reglesPath)) {
            $this->command->warn("  SKIP: {$config['regles_file']} introuvable dans {$config['folder']}");
            return;
        }
        $reglesJson = json_decode(file_get_contents($reglesPath), true);
        if ($reglesJson === null) {
            $this->command->error("  ERROR: JSON invalide dans {$config['regles_file']}");
            return;
        }

        $calculPy = null;
        if ($config['calcul_file'] !== null) {
            $calculPath = $folderPath . '/' . $config['calcul_file'];
            if (file_exists($calculPath)) {
                $calculPy = file_get_contents($calculPath);
            } else {
                $this->command->warn("  WARN: {$config['calcul_file']} introuvable — skill créé sans script Python");
            }
        }

        SkillsCatalog::updateOrCreate(
            ['skill_id' => $config['skill_id']],
            [
                'nom'         => $config['nom'],
                'code'        => $config['code'],
                'version'     => $config['version'],
                'type'        => $config['type'],
                'description' => $config['description'],
                'skill_md'    => $skillMd,
                'regles_json' => $reglesJson,
                'calcul_py'   => $calculPy,
                'tags'        => $config['tags'],
                'priority'    => $config['priority'],
                'active'      => true,
            ]
        );

        $this->command->info("  OK: {$config['code']} ({$config['skill_id']})");
    }
}
