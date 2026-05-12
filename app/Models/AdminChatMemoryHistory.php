<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminChatMemoryHistory extends Model
{
    protected $table = 'admin_chat_memory_history';

    public $timestamps = false;

    protected $fillable = ['memory_id', 'content'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function memory()
    {
        return $this->belongsTo(AdminChatMemory::class, 'memory_id');
    }
}
