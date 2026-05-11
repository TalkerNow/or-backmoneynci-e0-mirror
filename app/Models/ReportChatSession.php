<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportChatSession extends Model
{
    protected $table = 'report_chat_sessions';

    protected $fillable = [
        'analysis_report_id',
        'created_by',
    ];

    public function analysisReport()
    {
        return $this->belongsTo(AnalysisReport::class, 'analysis_report_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(ReportChatMessage::class, 'session_id')->orderBy('id');
    }
}
