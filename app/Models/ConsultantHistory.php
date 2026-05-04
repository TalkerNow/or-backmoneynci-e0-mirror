<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultantHistory extends Model
{
    public $timestamps = false;

    protected $table = 'consultant_history';

    protected $fillable = [
        'user_id',
        'consultant_id',
        'consultant_name',
        'changed_at',
    ];
}
