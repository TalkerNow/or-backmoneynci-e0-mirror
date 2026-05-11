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
        'carpimko',
        'regimes_points',
        'alertes',
        'totaux',
        'scenario_choisi',
        'date_retenue',
        'scenarios_choisis',
        'dates_retenues',
        'locked_at',
        'locked_by',
        'deleted_by',
    ];

    protected $casts = [
        'meta'              => 'array',
        'carriere'          => 'array',
        'cipav'             => 'array',
        'carpimko'          => 'array',
        'regimes_points'    => 'array',
        'alertes'           => 'array',
        'totaux'            => 'array',
        'scenario_choisi'   => 'array',
        'date_retenue'      => 'array',
        'scenarios_choisis' => 'array',
        'dates_retenues'    => 'array',
        'locked_at'         => 'datetime',
    ];

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
