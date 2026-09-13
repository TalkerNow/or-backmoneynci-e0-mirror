<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundEmail extends Model
{
    protected $table = 'inbound_emails';

    protected $fillable = [
        'gmail_message_id',
        'gmail_thread_id',
        'source',
        'from_email',
        'from_name',
        'to_email',
        'subject',
        'snippet',
        'body',
        'received_at',
        'gmail_permalink',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];
}
