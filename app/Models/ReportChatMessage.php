<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportChatMessage extends Model
{
    protected $table = 'report_chat_messages';

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'proposed_html',
        'applied_version_id',
    ];

    public function session()
    {
        return $this->belongsTo(ReportChatSession::class, 'session_id');
    }

    public function appliedVersion()
    {
        return $this->belongsTo(ReportVersion::class, 'applied_version_id');
    }
}
