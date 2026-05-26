<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisReport extends Model
{
    protected $table = 'analysis_reports';

    protected $fillable = [
        'user_id',
        'frozen_data_id',
        'skill_id',
        'result_json',
        'calcul_json',
        'restitution_json',
        'alertes_json',
        'arret_critique_json',
        'statut',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'result_json'         => 'array',
        'calcul_json'         => 'array',
        'restitution_json'    => 'array',
        'alertes_json'        => 'array',
        'arret_critique_json' => 'array',
        'validated_at'        => 'datetime',
    ];

    /**
     * Client associé
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Consultant qui a validé le rapport
     */
    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Audit logs associés à ce rapport
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'analysis_report_id');
    }

    /**
     * Session de chat IA pour éditer le livrable
     */
    public function chatSession()
    {
        return $this->hasOne(ReportChatSession::class, 'analysis_report_id');
    }

    /**
     * Historique des versions du HTML du livrable
     */
    public function versions()
    {
        return $this->hasMany(ReportVersion::class, 'analysis_report_id')->orderBy('id', 'desc');
    }

    /**
     * Données carrière figées liées à ce rapport
     */
    public function frozenData()
    {
        return $this->belongsTo(FrozenData::class, 'frozen_data_id');
    }

    /**
     * Vérifie si le rapport a un arrêt critique
     */
    public function hasArretCritique(): bool
    {
        return !empty($this->arret_critique_json);
    }

    /**
     * Retourne les alertes par niveau (ROUGE, ORANGE, JAUNE)
     */
    public function getAlertesByNiveau(string $niveau): array
    {
        $alertes = $this->alertes_json ?? [];

        return array_filter($alertes, function ($alerte) use ($niveau) {
            return ($alerte['niveau'] ?? '') === $niveau;
        });
    }
}
