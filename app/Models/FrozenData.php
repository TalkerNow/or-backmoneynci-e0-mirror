<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FrozenData extends Model
{
    use SoftDeletes;

    protected $table = 'frozen_data';

    protected $fillable = [
        'user_id',
        'source',
        'meta',
        'carriere',
        'cipav',
        'alertes',
        'totaux',
        'scenario_choisi',
        'locked_at',
        'locked_by',
        'deleted_by',
    ];

    protected $casts = [
        'meta'            => 'array',
        'carriere'        => 'array',
        'cipav'           => 'array',
        'alertes'         => 'array',
        'totaux'          => 'array',
        'scenario_choisi' => 'array',
        'locked_at'       => 'datetime',
    ];

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
