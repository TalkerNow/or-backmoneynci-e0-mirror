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

    /**
     * Historique des versions de skill_md (ordre desc).
     */
    public function history()
    {
        return $this->hasMany(SkillsCatalogHistory::class, 'skills_catalog_id')
                    ->orderBy('version', 'desc');
    }

    /**
     * Override boot : crée automatiquement une entrée d'historique
     * dès que skill_md est modifié, en y stockant l'ANCIENNE valeur.
     * Calqué sur App\Models\Prompt::boot().
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($skill) {
            if ($skill->isDirty('skill_md') || $skill->isDirty('regles_json')) {
                $latestVersion = $skill->history()->max('version') ?? 0;

                SkillsCatalogHistory::create([
                    'skills_catalog_id' => $skill->id,
                    'version'           => $latestVersion + 1,
                    'skill_md'          => $skill->getOriginal('skill_md'),
                    'regles_json'       => $skill->getOriginal('regles_json'),
                    'created_by'        => auth()->id(),
                ]);
            }
        });
    }
}
