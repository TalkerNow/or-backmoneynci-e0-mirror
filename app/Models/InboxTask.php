<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxTask extends Model
{
    protected $table = 'inbox_tasks';

    protected $fillable = [
        'user_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
