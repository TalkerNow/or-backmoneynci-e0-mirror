<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationArchive extends Model
{
    protected $table = 'conversation_archives';

    protected $fillable = [
        'summary',
        'messages',
        'user_id',
        'invisible',
    ];

    protected $casts = [
        'messages' => 'array',
        'invisible' => 'boolean',
    ];

    /**
     * Get the user associated with this conversation archive.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

