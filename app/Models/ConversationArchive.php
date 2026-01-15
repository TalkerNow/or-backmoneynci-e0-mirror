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
}

