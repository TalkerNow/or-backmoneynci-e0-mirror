<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsCatalog extends Model
{
    protected $table = 'skills_catalog';

    protected $fillable = [
        'skill_id',
        'nom',
        'code',
        'version',
        'type',
        'description',
        'skill_md',
        'regles_json',
        'calcul_py',
        'tags',
        'priority',
        'active',
    ];

    protected $casts = [
        'regles_json' => 'array',
        'tags'        => 'array',
        'active'      => 'boolean',
    ];

    /**
     * Scope : uniquement les skills actifs
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope : chercher par code (ex: "CNAV", "RACL")
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }
}
