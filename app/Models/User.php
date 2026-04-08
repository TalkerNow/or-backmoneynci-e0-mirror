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

    public function simulatorDifficultyResults()
    {
        return $this->hasMany(SimulatorDifficultyResult::class, 'user_id');
    }

    public function suiviAvancementsByUser()
    {
        return $this->hasMany(SuiviAvancement::class, 'client_id');
    }

    public function callReportsAsClient()
    {
        return $this->hasMany(CallReport::class, 'client_id');
    }

    public function callReportsAsAdmin()
    {
        return $this->hasMany(CallReport::class, 'admin_id');
    }

    public function inboxTasksAsUser()
    {
        return $this->hasMany(InboxTask::class, 'user_id');
    }

    public function inboxTasksAsAdmin()
    {
        return $this->hasMany(InboxTask::class, 'admin_id');
    }

    public function extractionDataRis()
    {
        return $this->hasMany(ExtractionDataRis::class, 'user_id');
    }

    public function files()
    {
        return $this->hasMany(Files::class, 'user_id');
    }

    public function kpisAsAdmin()
    {
        return $this->hasMany(Kpi::class, 'admin_id');
    }

    public function simulatorErrorTagsAsUser()
    {
        return $this->hasMany(SimulatorErrorTag::class, 'user_id');
    }

    public function simulatorErrorTagsAsAdmin()
    {
        return $this->hasMany(SimulatorErrorTag::class, 'admin_id');
    }

    public function tasksAsCreator()
    {
        return $this->hasMany(Tasks::class, 'creator_id');
    }

    public function tasksAsCustomer()
    {
        return $this->hasMany(Tasks::class, 'customer_id');
    }

    public function userFunds()
    {
        return $this->hasMany(UserFunds::class, 'user_id');
    }

    public function prompts()
    {
        return $this->hasMany(Prompt::class, 'created_by');
    }

    public function promptHistories()
    {
        return $this->hasMany(PromptHistory::class, 'created_by');
    }
}
