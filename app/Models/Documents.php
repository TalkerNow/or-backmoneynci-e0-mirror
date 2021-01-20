<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Documents extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'link_to_documents', 'type', 'document_state', 'date', 'comment', 'advanced_payment', 'id', 'user_id','values'
    ];
    public function user() {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}
