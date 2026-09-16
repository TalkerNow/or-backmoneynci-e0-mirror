<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundEmail extends Model
{
    protected $table = 'inbound_emails';

    protected $fillable = [
        'external_id',
        'gmail_message_id',
        'gmail_thread_id',
        'source',
        'client_id',
        'from_email',
        'from_name',
        'to_email',
        'subject',
        'snippet',
        'body',
        'received_at',
        'gmail_permalink',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'read_at' => 'datetime',
        'is_read' => 'boolean',
    ];
}
