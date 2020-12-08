<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Services extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'description', 'variable', 'value', 'variable1', 'value1', 'total_ht', 'total_ttc', 'tva', 'id', 'status', 'document_id', 'parent_id'
    ];
}