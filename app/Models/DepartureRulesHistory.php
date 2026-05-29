<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartureRulesHistory extends Model
{
    protected $table = 'departure_rules_history';

    const UPDATED_AT = null;

    protected $fillable = [
        'rules_json',
        'saved_by',
    ];

    protected $casts = [
        'rules_json' => 'array',
    ];

    public function savedBy()
    {
        return $this->belongsTo(User::class, 'saved_by');
    }
}
