<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrozenData extends Model
{
    protected $table = 'frozen_data';

    protected $fillable = [
        'user_id',
        'source',
        'meta',
        'carriere',
        'cipav',
        'alertes',
        'totaux',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'meta'      => 'array',
        'carriere'  => 'array',
        'cipav'     => 'array',
        'alertes'   => 'array',
        'totaux'    => 'array',
        'locked_at' => 'datetime',
    ];

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
