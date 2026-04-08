<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxTask extends Model
{
    protected $table = 'inbox_tasks';

    protected $fillable = [
        'user_id',
        'admin_id',
        'date',
        'data',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
