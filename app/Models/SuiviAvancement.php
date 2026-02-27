<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuiviAvancement extends Model
{
    protected $table = 'suivi_avancement'; // utile si tu restes au singulier

    protected $fillable = [
        'client_id',
        'facture_id',
        'step1_completed_at',
        'step2_completed_at',
        'step3_completed_at',
        'step4_completed_at',
        'step5_completed_at',
        'step6_completed_at',
        'step7_completed_at',
        'step8_completed_at',
    ];

    protected $casts = [
        'step1_completed_at' => 'datetime',
        'step2_completed_at' => 'datetime',
        'step3_completed_at' => 'datetime',
        'step4_completed_at' => 'datetime',
        'step5_completed_at' => 'datetime',
        'step6_completed_at' => 'datetime',
        'step7_completed_at' => 'datetime',
        'step8_completed_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function facture()
    {
        return $this->belongsTo(Documents::class, 'facture_id');
    }
}
