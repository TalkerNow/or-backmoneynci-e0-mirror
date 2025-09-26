<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kpi extends Model
{
    protected $table = 'kpis';

    // aucun champ obligatoire côté validation : on rend tout fillable
    protected $fillable = [
        'kpi_date', 'admin_id', 'objet', 'action',
        'nom_prenom', 'email', 'telephone', 'note',
    ];

    protected $casts = [
        'kpi_date' => 'date', // pratique si tu fournis une date
    ];

    // relation optionnelle vers l’admin (users.id)
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
