<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportedError extends Model
{
    protected $fillable = [
        'user_id',
        'client_id',
        'section',
        'description',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
