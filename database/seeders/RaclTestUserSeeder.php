<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Crée un client test complet éligible au RACL.
 *
 * Profil RACL :
 *   - Né le 15/03/1964 → génération 1964 → durée requise 171 trimestres
 *   - Début activité en 1980 à 16 ans → palier "avant 18 ans" → départ à 60 ans
 *   - Trimestres requis pour palier 60 ans : 171 - 4 = 167
 *   - Carrière 1980–2024 → 174 trimestres cotisés → ÉLIGIBLE, départ 03/2024
 *
 * Usage : php artisan db:seed --class=RaclTestUserSeeder
 */
class RaclTestUserSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. User ──────────────────────────────────────────────────────────
        $email = 'racl.test@optionretraite.fr';

        $existingUserId = DB::table('users')->where('email', $email)->value('id');

        if ($existingUserId) {
            $userId = $existingUserId;
            $this->command->warn("User #{$userId} existe déjà — frozen_data mis à jour.");
        } else {
            $userId = DB::table('users')->insertGetId([
                'name'         => 'MARTIN Jean-Pierre',
                'email'        => $email,
                'password'     => Hash::make('TestRACL2026!'),
                'valid_account'=> true,
                'role'         => 'Client',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $this->command->info("User créé : #{$userId} ({$email})");
        }

        // ── 2. Personal informations ─────────────────────────────────────────
        $hasPi = DB::table('personal_informations')->where('user_id', $userId)->exists();
        if (!$hasPi) {
            DB::table('personal_informations')->insert([
                'id'               => $userId, // La JOIN dans UsersController est sur personal_informations.id = users.id
                'user_id'          => $userId,
                'civility'         => 'M',
                'first_name'       => 'Jean-Pierre',
                'last_name'        => 'MARTIN',
                'birth_date'       => '1964-03-15',
                'birth_place'      => 'Lyon',
                'martial_status'   => 'married',
                'children_number'  => 2,
                'mobile_number'    => '0612345678',
                'personal_address' => '12 rue des Lilas',
                'personal_zip_code'=> '69001',
                'personal_city'    => 'Lyon',
                'personal_country' => 'France',
                'secu_social'      => '1640369123456',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // ── 3. Frozen data ───────────────────────────────────────────────────
        // Carrière 1980–2024 (45 ans) — début à 16 ans, palier RACL 60 ans
        $carriere = [];
        for ($annee = 1980; $annee <= 2024; $annee++) {
            // Quelques années partielles pour réalisme
            $tc = match(true) {
                $annee === 1980            => 2, // début en milieu d'année (16 ans)
                in_array($annee, [1983, 1990, 1995, 2001]) => 3, // congés/maladie
                $annee >= 2020             => 4,
                default                    => 4,
            };

            $salaire = 12000 + ($annee - 1980) * 1200; // Progression salariale réaliste

            $carriere[] = [
                'annee'                => $annee,
                'salaire_brut'         => $salaire,
                'salaire_revalo'       => (int) round($salaire * 1.38),
                'trimestres_cotises'   => $tc,
                'trimestres_assimiles' => 0,
                'trimestres_rachetes'  => 0,
                'points_agirc_arrco'   => round($salaire / 18.30, 2),
                'points_ircantec'      => 0,
                'points_rci'           => 0,
            ];
        }

        $totalCot = array_sum(array_column($carriere, 'trimestres_cotises')); // 174
        $totalAss = 0;
        $totalPtsAgirc = array_sum(array_column($carriere, 'points_agirc_arrco'));

        $meta = [
            'nom'            => 'MARTIN',
            'prenom'         => 'Jean-Pierre',
            'date_naissance' => '1964-03-15', // YYYY-MM-DD
            'sexe'           => 'M',
            'nombre_enfants' => 2,
            'nir'            => '1640369123456', // NIR fictif cohérent (1=M, 64=1964, 03=mars, 69=Rhône)
            'valide_le'      => now()->toDateString(),
        ];

        $totaux = [
            'trimestres_cotises'      => $totalCot,
            'trimestres_assimiles'    => $totalAss,
            'trimestres_total'        => $totalCot + $totalAss,
            'trimestres_tous_regimes' => $totalCot + $totalAss,
            'trimestres_requis'       => 171, // génération 1964
            'trimestres_par_regime'   => [
                'cnav'     => $totalCot,
                'cipav'    => 0,
                'ircantec' => 0,
                'rci'      => 0,
                'msa'      => 0,
            ],
            'points_officiels' => [
                'agirc_arrco' => ['total_points' => round($totalPtsAgirc, 2), 'valeur_point' => 1.4386],
                'cipav'       => ['points_base' => 0, 'points_complementaire' => 0],
                'ircantec'    => ['total_points' => 0, 'valeur_point' => 0.56357],
                'rci'         => ['total_points' => 0, 'valeur_point' => 1.280],
            ],
        ];

        $frozenPayload = [
            'user_id'    => $userId,
            'source'     => 'RACL_TEST_SEED',
            'meta'       => json_encode($meta),
            'carriere'   => json_encode($carriere),
            'cipav'      => json_encode([]),
            'alertes'    => json_encode([]),
            'totaux'     => json_encode($totaux),
            'locked_at'  => now(),
            'locked_by'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $existingFrozen = DB::table('frozen_data')->where('user_id', $userId)->first();
        if ($existingFrozen) {
            DB::table('frozen_data')->where('user_id', $userId)
                ->update(array_merge($frozenPayload, ['updated_at' => now()]));
        } else {
            DB::table('frozen_data')->insert($frozenPayload);
        }

        $this->command->info("─────────────────────────────────────────");
        $this->command->info("✓ Client RACL test prêt :");
        $this->command->info("  ID            : #{$userId}");
        $this->command->info("  Email         : {$email}  /  Mdp : TestRACL2026!");
        $this->command->info("  Né le         : 15/03/1964 (génération 1964)");
        $this->command->info("  Début carrière: 1980 à 16 ans → palier avant 18 ans");
        $this->command->info("  Trim. cotisés : {$totalCot} / 167 requis → ÉLIGIBLE");
        $this->command->info("  Départ prévu  : 03/2024 (60 ans)");
        $this->command->info("─────────────────────────────────────────");
    }
}
