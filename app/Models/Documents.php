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
        'id',
        'link_to_documents',
        'type',
        'document_state',
        'comment',
        'payment_method',
        'advanced_payment',
        'pre_payment',
        'end_payment',
        'status_payment',
        'subscribe_services',
        'values',
        'user_id',
        'parent_id',
        'deposit_date',
        'sold_date',
        'creator_id',
        'sold_dates',
        'acompte_dates'
    ];
    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}
