<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminChatMemory extends Model
{
    protected $table = 'admin_chat_memory';

    public $timestamps = false;

    protected $fillable = ['user_id', 'content'];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function history()
    {
        return $this->hasMany(AdminChatMemoryHistory::class, 'memory_id')->orderBy('created_at', 'desc');
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($memory) {
            if ($memory->isDirty('content')) {
                AdminChatMemoryHistory::create([
                    'memory_id' => $memory->id,
                    'content'   => $memory->getOriginal('content'),
                ]);
            }
        });
    }
}
