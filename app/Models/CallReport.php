<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallReport extends Model
{
    protected $table = 'call_report';

    protected $fillable = [
        'client_id',
        'admin_id',
        'call_report',
    ];
}
