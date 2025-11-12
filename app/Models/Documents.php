<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documents extends Model
{
    use HasFactory;

    protected $fillable = [
        // ⚠️ enlève 'id' d'ici
        'link_to_documents','type','document_state','comment','payment_method',
        'advanced_payment','pre_payment','end_payment','status_payment',
        'subscribe_services','values','user_id','parent_id','deposit_date',
        'sold_date','creator_id','acompte_dates','sold_dates',
    ];

    protected $casts = [
        'values'         => 'array',
        'sold_dates'     => 'array',
        'acompte_dates'  => 'array',
    ];

    // Valeurs par défaut envoyées si rien n’est fourni
    protected $attributes = [
        'sold_dates'    => '[]',
        'acompte_dates' => '[]',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

