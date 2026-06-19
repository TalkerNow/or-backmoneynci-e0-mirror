<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulatorChatSession extends Model
{
    protected $fillable = ['user_id', 'customer_id', 'title', 'context_page'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function messages()
    {
        return $this->hasMany(SimulatorChatMessage::class, 'session_id')->orderBy('created_at');
    }
}
