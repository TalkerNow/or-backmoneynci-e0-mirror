<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationArchive extends Model
{
    protected $table = 'conversation_archives';

    protected $fillable = [
        'summary',
        'source',
        'messages',
        'user_id',
        'invisible',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'messages' => 'array',
        'invisible' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the user associated with this conversation archive.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
