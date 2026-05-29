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

    /** Statuts qui correspondent à une livraison/validation du rapport. */
    public const DELIVERY_STATUTS = ['valide', 'validé', 'livre', 'livré'];

    /**
     * Garde-fou central : un rapport avec arrêt critique (règle Gate #2
     * déclenchée) ne peut pas passer à un statut de livraison.
     */
    protected static function booted()
    {
        static::saving(function (self $report) {
            if (self::isDeliveryStatut($report->statut) && $report->hasArretCritique()) {
                throw new \RuntimeException(
                    "Livraison bloquée : ce rapport contient un arrêt critique (règle Gate #2). "
                    . "Corrigez l'incohérence avant de valider/livrer."
                );
            }
        });
    }

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
     * Un statut donné correspond-il à une livraison/validation ?
     */
    public static function isDeliveryStatut(?string $statut): bool
    {
        return in_array((string) $statut, self::DELIVERY_STATUTS, true);
    }

    /**
     * Le rapport peut-il être livré/validé ? Faux s'il a un arrêt critique.
     */
    public function canBeDelivered(): bool
    {
        return !$this->hasArretCritique();
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
