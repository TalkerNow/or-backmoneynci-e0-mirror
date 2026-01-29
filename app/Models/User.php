<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'id',
        'parent_id',
        'business_introducer_id',
        'subscribe_services',
        'status',
        'status_fa',
        'status_update_date',
        'chatbot_id',
        'diag_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    public function personalInformation()
    {
        return $this->belongsTo('app\Models\PersonalInformations', 'id');
    }
    public function parent()
    {
        return $this->belongsTo('app\Models\User', 'parent_id');
    }

    public function business_introducer()
    {
        return $this->belongsTo('app\Models\User', 'business_introducer_id');
    }

    public function documents()
    {
        return $this->hasMany('App\Models\Documents', 'user_id');
    }
    public function oldClients()
    {
        return $this->belongsTo('app\Models\OldClients');
    }

    public function userKanbans()
    {
        return $this->hasMany(UserKanban::class, 'user_id');
    }

    public function conversationArchives()
    {
        return $this->hasMany(ConversationArchive::class, 'user_id');
    }
}
