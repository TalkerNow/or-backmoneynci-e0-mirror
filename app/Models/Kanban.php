<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kanban extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'color',
        'order',
    ];

    /**
     * Get all user kanban cards for this kanban column.
     */
    public function userKanbans()
    {
        return $this->hasMany(UserKanban::class, 'kanban_id');
    }
}
