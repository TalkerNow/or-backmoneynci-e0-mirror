<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminChatMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 'role', 'content', 'metadata', 'applied_modification_id'];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(AdminChatSession::class, 'session_id');
    }

    public function snapshot()
    {
        return $this->belongsTo(AdminChatSnapshot::class, 'applied_modification_id');
    }
}
