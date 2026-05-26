<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminChatSession extends Model
{
    protected $fillable = ['user_id', 'title', 'context_page'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(AdminChatMessage::class, 'session_id')->orderBy('created_at');
    }

    public function snapshots()
    {
        return $this->hasMany(AdminChatSnapshot::class, 'session_id');
    }
}
