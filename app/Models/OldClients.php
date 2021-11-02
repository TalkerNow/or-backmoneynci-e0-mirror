<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OldClients extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'clcleunik', 'cl_emcleunik_resp', 'cl_emcleunik_cons', 'cl_civilite', 'cl_nom_jf', 'cl_nom', 'cl_prenom', 'cl_adr', 'cl_cp', 'cl_ville', 'cl_pays', 'cl_nom_soc', 'cl_adr_soc', 'cl_cp_soc', 'cl_ville_soc', 'cl_tel_bur', 'cl_tel_dom', 'cl_tel_port', 'cl_mail', 'cl_ss1', 'cl_ss2', 'cl_ne_le', 'cl_ne_a', 'cl_nb_enf', 'cl_nationalite', 'cl_sifacleunik', 'cl_date_sit_fam', 'cl_date_retraite', 'cl_conjoint_nom', 'cl_conjoint_prenom', 'cl_conjoint_ss1', 'cl_conjoint_ss2', 'cl_conjoint_ne_le', 'cl_conjoint_decede_le', 'cl_etude_sup', 'cl_etude_nb_annee', 'cl_tyetcleunik', 'cl_diplome_obtenu', 'cl_service_mil', 'cl_annee_service_mil', 'cl_duree_service_mil', 'cl_tysemicleunik', 'cl_date', 'cl_source', 'cl_memo', 'cl_assurance', 'findossier'
    ];
}
