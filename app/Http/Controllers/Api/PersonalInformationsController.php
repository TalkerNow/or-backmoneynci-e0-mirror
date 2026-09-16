<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\FrozenData;
use App\Models\PersonalInformations;
use App\Models\User;
use Dotenv\Validator;
use Illuminate\Http\Request;
use phpDocumentor\Reflection\Types\Nullable;

class PersonalInformationsController extends Controller
{
    public function index()
    {

    }

    public function store(Request $request)
    {
        $result = PersonalInformations::create($request->all());
        return response()->json([
            'user_id' => $request->id
        ]);
    }

    public function show($id)
    {

    }

    public function update(Request $request, $id)
    {
        $user = $this->get_user($id);
        $information = $this->get_personal_information($id);

        if ($information == null)
            return response()->json(['error' => 'User does not exist'], 500);
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
        $request->validate([
            'civility' => 'nullable',
            'first_name' => 'nullable',
            'last_name' => 'nullable',
            'maiden' => 'nullable',
            'birth_date' => 'nullable',
            'martial_status' => 'nullable',
            'maiden_name' => 'nullable',
            'birth_place' => 'nullable',
            'children_number' => 'nullable',
            'statut_pro' => 'nullable',
            'nombre_enfants_handicapes' => 'nullable',
            'mobile_number' => 'nullable',
            'office_number' => 'nullable',
            'personal_address' => 'nullable',
            'personal_address_2' => 'nullable',
            'personal_zip_code' => 'nullable',
            'personal_city' => 'nullable',
            'personal_country' => 'nullable',
            'society_name' => 'nullable',
            'society_address' => 'nullable',
            'society_address_2' => 'nullable',
            'society_zip_code' => 'nullable',
            'society_city' => 'nullable',
            'society_country' => 'nullable',
            'notes' => 'nullable',
            'business_introducer_id' => 'nullable',
            'secu_social' => 'nullable',
            'secu_social_key' => 'nullable',
        ]);
        $information->update($request->all());

        // Synchronise le bloc meta du dernier frozen_data pour ce user (sans toucher carriere/totaux/cipav).
        // Évite les divergences silencieuses entre la fiche client et le snapshot utilisé par le simulateur.
        $this->syncFrozenDataMeta((int) $id, $information->fresh());
    }

    public function destroy($id)
    {

    }

    private function syncFrozenDataMeta(int $userId, $info): void
    {
        $frozen = FrozenData::where('user_id', $userId)->latest()->first();
        if (! $frozen) {
            return; // Pas de frozen_data : rien à synchroniser, le prochain "Geler" prendra les valeurs à jour.
        }

        $civility = strtolower(trim((string) ($info->civility ?? '')));
        $sexe = null;
        if (in_array($civility, ['madame', 'mme', 'mlle', 'mademoiselle'], true)) {
            $sexe = 'F';
        } elseif (in_array($civility, ['monsieur', 'mr', 'm.'], true)) {
            $sexe = 'M';
        }

        $meta = is_array($frozen->meta) ? $frozen->meta : [];
        $meta['nom']            = $info->last_name      ?? ($meta['nom']            ?? '');
        $meta['prenom']         = $info->first_name     ?? ($meta['prenom']         ?? '');
        $meta['civilite']       = $info->civility       ?? ($meta['civilite']       ?? '');
        $meta['date_naissance'] = $info->birth_date     ?? ($meta['date_naissance'] ?? null);
        $meta['nombre_enfants'] = (int) ($info->children_number ?? ($meta['nombre_enfants'] ?? 0));
        $statut = strtolower(trim((string) ($info->statut_pro ?? '')));
        $meta['statut'] = (strpos($statut, 'fonct') === 0 || $statut === 'public') ? 'fonctionnaire'
            : ($statut !== '' ? 'prive' : ($meta['statut'] ?? 'prive'));
        $meta['nombre_enfants_handicapes'] = (int) ($info->nombre_enfants_handicapes ?? ($meta['nombre_enfants_handicapes'] ?? 0));
        $meta['sexe']           = $sexe                 ?? ($meta['sexe']           ?? null);
        $meta['nir']            = $info->secu_social    ?? ($meta['nir']            ?? null);
        $meta['valide_le']      = now()->toDateString();
        $meta['synced_from_user_at'] = now()->toIso8601String();

        $frozen->meta = $meta;
        $frozen->save();
    }
}
