<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Tasks extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'creator_id', 'customer_id', 'title', 'desc', 'isCompleted', 'isRead', 'isImportant', 'type','end_date'
    ];
    public function taskCreator() {
        return $this->belongsTo('App\Models\User', 'creator_id');
    }
    public function taskCustomer() {
        return $this->belongsTo('App\Models\User', 'customer_id');
    }
}
