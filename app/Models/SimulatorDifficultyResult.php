<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulatorDifficultyResult extends Model
{
    protected $table = 'simulator_difficulty_results';

    protected $fillable = [
        'external_contact_id','email','nom','prenom','civilite','statut',
        'date_naissance','code_postal','nbr_enfants','score',
        'q1','q2','q3','q4','q5','q6','q7','q8','q9','q10',
        'newsletter','automation_recap_retraite','date_depart',
        'email_blacklisted','sms_blacklisted','list_ids','list_unsubscribed',
        'raw_payload','external_created_at','external_modified_at',
        'invisible', 'user_id',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_depart' => 'date',
        'invisible' => 'boolean',
        'newsletter' => 'boolean',
        'automation_recap_retraite' => 'boolean',
        'email_blacklisted' => 'boolean',
        'sms_blacklisted' => 'boolean',
        'list_ids' => 'array',
        'raw_payload' => 'array',
        'external_created_at' => 'datetime',
        'external_modified_at' => 'datetime',
    ];

    /**
     * Get the user associated with this simulator difficulty result.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
