<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulatorChatMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 'role', 'content', 'metadata'];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(SimulatorChatSession::class, 'session_id');
    }
}
