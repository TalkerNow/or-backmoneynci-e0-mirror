<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulatorErrorTag extends Model
{
    protected $table = 'simulator_error_tags';

    protected $fillable = [
        'admin_id',
        'user_id',
        'document_id',
        'error_handling',
        'tag',
    ];

    protected $casts = [
        'tag' => 'array',
    ];
}
