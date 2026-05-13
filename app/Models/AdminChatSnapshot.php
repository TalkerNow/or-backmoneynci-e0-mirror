<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminChatSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id', 'message_id', 'user_id',
        'entity_type', 'entity_id',
        'content_before', 'content_after',
        'applied_at', 'reverted_at',
    ];

    protected $casts = [
        'applied_at'  => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(AdminChatSession::class, 'session_id');
    }

    public function message()
    {
        return $this->belongsTo(AdminChatMessage::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
