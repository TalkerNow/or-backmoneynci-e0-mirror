<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportVersion extends Model
{
    protected $table = 'report_versions';

    protected $fillable = [
        'analysis_report_id',
        'html_content',
        'source',
        'source_message_id',
        'created_by',
    ];

    public function analysisReport()
    {
        return $this->belongsTo(AnalysisReport::class, 'analysis_report_id');
    }

    public function sourceMessage()
    {
        return $this->belongsTo(ReportChatMessage::class, 'source_message_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
